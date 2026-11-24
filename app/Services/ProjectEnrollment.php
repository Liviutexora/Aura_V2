<?php

namespace App\Services;

use App\Models\Category;
use App\Models\MissionTemplate;
use App\Models\Opportunity;
use App\Models\Project;
use App\Models\ProjectMission;
use App\Models\ProjectMissionStep;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProjectEnrollment
{
    public function __construct(private OpportunityCatalog $catalog, private SubscriptionAccess $access) {}

    public function accept(User $owner, Opportunity $offer, User $actor, array $attestations = [], ?MissionTemplate $extra = null, ?string $type = null): Project
    {
        return DB::transaction(function () use ($owner, $offer, $actor, $attestations, $extra, $type) {
            Category::whereKey($offer->category_id)->lockForUpdate()->firstOrFail();
            $offer = Opportunity::lockForUpdate()->findOrFail($offer->id);
            $owner = User::lockForUpdate()->findOrFail($owner->id);
            abort_unless($owner->isActive(), 403);
            $project = $owner->projects()->where('opportunity_id', $offer->id)->lockForUpdate()->first();
            abort_if($project && $type !== null && $type !== $project->participation_type, 409);
            if (! $project) {
                if (! $this->catalog->published()->whereKey($offer->id)->exists()) {
                    throw ValidationException::withMessages(['opportunity' => 'Înscrierile pentru această oportunitate sunt închise.']);
                }
                $type ??= 'explorer';
                $this->access->authorizeEnrollment($owner, $offer, $type);
                $rules = $this->catalog->snapshot($offer, $owner, $actor, $attestations);
                $accepted = now();
                $project = $owner->projects()->create([
                    'opportunity_id' => $offer->id, 'title' => $offer->title, 'source' => $owner->is($actor) ? 'self' : 'admin',
                    'created_by' => $actor->id, 'status' => 'accepted', 'accepted_at' => $accepted,
                    'participation_type' => $type,
                    'due_on' => $offer->project_due_on ?? $this->relativeDate($accepted, $offer->project_duration_days),
                    'opportunity_version' => $offer->version, 'offer_snapshot' => [...$offer->attributesToArray(), 'category_name' => $offer->category->name], 'eligibility_snapshot' => $rules,
                ]);
                $this->event($project, $actor, 'project_accepted', null, null, null, 'accepted');
                $templates = $offer->templates()->where('status', 'active')->where('assign_on_accept', true)->orderBy('position')->get();
                if (! $templates->contains('is_required', true)) {
                    throw ValidationException::withMessages(['opportunity' => 'Parcursul obligatoriu este indisponibil.']);
                }
                foreach ($templates as $template) {
                    $this->allocate($project, $template, $actor);
                }
            }
            if ($extra) {
                $this->allocate($project, $offer->templates()->findOrFail($extra->id), $actor);
            }

            return $project;
        }, 3);
    }

    public function start(Project $project, User $actor): void
    {
        DB::transaction(function () use ($project, $actor) {
            $locked = $actor->projects()->lockForUpdate()->findOrFail($project->id);
            if ($locked->status === 'accepted') {
                $locked->update(['status' => 'active', 'started_at' => now()]);
                $this->event($locked, $actor, 'project_started', null, null, 'accepted', 'active');
            }
        }, 3);
    }

    private function allocate(Project $project, MissionTemplate $template, User $actor): void
    {
        if ($project->missions()->where('template_id', $template->id)->exists()) {
            return;
        }
        abort_if($project->status === 'completed' || $template->status !== 'active', 409);
        $steps = $template->steps()->where('status', 'active')->get();
        if ($steps->isEmpty()) {
            throw ValidationException::withMessages(['template_id' => 'Șablonul nu conține pași activi.']);
        }
        $mission = $project->missions()->create([
            ...$template->only('title', 'description', 'required_action', 'verification_method', 'xp_points', 'is_required'),
            ...$this->deadline($project, $template), 'assigned_by' => $actor->id, 'assigned_at' => now(),
            'template_id' => $template->id, 'template_version' => $template->version, 'status' => 'assigned',
            'position' => 1 + (int) $project->missions()->max('position'),
        ]);
        foreach ($steps as $step) {
            $mission->steps()->create([
                ...$step->only('title', 'description', 'instructions', 'required_action', 'position', 'xp_points', 'confirms_receipt'),
                ...$this->deadline($project, $step), 'verification_method' => $step->verification_method ?? 'user_declaration',
                'source_step_id' => $step->id, 'status' => 'pending',
            ]);
        }
        $this->event($project, $actor, 'mission_assigned', $mission, null, null, 'assigned');
    }

    private function deadline(Project $project, Model $definition): array
    {
        $due = $definition->deadline_on;
        $anchorId = null;
        if ($definition->deadline_anchor === 'accepted') {
            $due = $this->relativeDate($project->accepted_at, $definition->deadline_after_days);
        } elseif ($definition->anchor_step_id) {
            $anchorId = ProjectMissionStep::whereHas('mission', fn ($q) => $q->where('project_id', $project->id))
                ->where('source_step_id', $definition->anchor_step_id)->value('id');
            if (! $anchorId) {
                throw ValidationException::withMessages(['anchor_step_id' => 'Pasul de referință trebuie alocat înaintea termenului dependent.']);
            }
            $event = $project->events()->where('step_id', $anchorId)->where('event_type', $definition->deadline_anchor)->oldest('id')->first();
            if ($event) {
                $due = $this->relativeDate($event->occurred_at, $definition->deadline_after_days);
            }
        }

        return ['deadline_anchor' => $definition->deadline_anchor, 'anchor_step_id' => $anchorId,
            'deadline_after_days' => $definition->deadline_after_days, 'due_on' => $due];
    }

    public function relativeDate(CarbonInterface $anchor, ?int $days): ?string
    {
        return $days === null ? null : $anchor->copy()->setTimezone(OpportunityCatalog::TIMEZONE)->addDays($days)->toDateString();
    }

    public function event(Project $project, User $actor, string $type, ?ProjectMission $mission = null, ?ProjectMissionStep $step = null, ?string $from = null, ?string $to = null, array $metadata = []): void
    {
        $time = now();
        $project->events()->create(['mission_id' => $mission?->id, 'step_id' => $step?->id, 'actor_user_id' => $actor->id,
            'event_type' => $type, 'event_key' => (string) Str::uuid(), 'occurred_at' => $time, 'from_status' => $from, 'to_status' => $to, 'metadata' => $metadata]);
        if ($step && in_array($type, ['step_checked', 'step_approved'], true)) {
            foreach ([ProjectMission::class, ProjectMissionStep::class] as $class) {
                $dependents = $class::where('anchor_step_id', $step->id)->where('deadline_anchor', $type)->whereNull('due_on')->get();
                foreach ($dependents as $dependent) {
                    $dependent->update(['due_on' => $this->relativeDate($time, $dependent->deadline_after_days)]);
                }
            }
        }
    }
}
