<?php

namespace App\Services;

use App\Models\ExternalEarning;
use App\Models\MissionSubmission;
use App\Models\Project;
use App\Models\ProjectMission;
use App\Models\ProjectMissionStep;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MissionProgress
{
    public function __construct(private ProjectEnrollment $enrollment) {}

    public function submit(User $actor, ProjectMission $target, ?int $stepId, array $input): void
    {
        DB::transaction(function () use ($actor, $target, $stepId, $input) {
            $project = $actor->projects()->lockForUpdate()->findOrFail($target->project_id);
            $mission = $project->missions()->lockForUpdate()->findOrFail($target->id);
            $step = $stepId ? $mission->steps()->lockForUpdate()->findOrFail($stepId) : null;
            $key = hash('sha256', $actor->id.'|'.$input['request_key']);
            $fingerprint = hash('sha256', json_encode([$mission->id, $stepId, $input['checked'] ?? true, $input['evidence_text'] ?? null,
                $input['file_hash'] ?? null, $input['amount'] ?? null, $input['currency'] ?? null, $input['earning_key'] ?? null, $input['description'] ?? null], JSON_THROW_ON_ERROR));
            $prior = $project->events()->where('event_key', $key)->first();
            if ($prior) {
                abort_unless(($prior->metadata['fingerprint'] ?? null) === $fingerprint, 409);

                return;
            }
            abort_if($project->status === 'accepted', 409, 'Începe proiectul înainte de a executa misiunea.');
            $checked = $input['checked'] ?? true;
            if (! $checked) {
                abort_unless($step && $step->verification_method === 'user_declaration' && in_array($mission->status, ['assigned', 'in_progress', 'needs_changes'], true), 409);
                abort_if($mission->submissions()->whereNull('step_id')->exists(), 409, 'Misiunea a fost deja trimisă la verificarea finală.');
                if ($step->status === 'completed') {
                    $step->update(['status' => 'pending', 'completed_at' => null]);
                    $this->enrollment->event($project, $actor, 'step_unchecked', $mission, $step, 'completed', 'pending');
                }
            } else {
                $this->recordSubmission($project, $mission, $step, $actor, $key, $input);
            }
            $project->events()->create(['event_key' => $key, 'event_type' => 'request_processed', 'actor_user_id' => $actor->id, 'mission_id' => $mission->id,
                'step_id' => $stepId, 'occurred_at' => now(), 'metadata' => ['fingerprint' => $fingerprint]]);
        }, 3);
    }

    private function recordSubmission(Project $project, ProjectMission $mission, ?ProjectMissionStep $step, User $actor, string $key, array $input): void
    {
        $method = $step?->verification_method ?? $mission->verification_method;
        abort_if($mission->status === 'completed' || $step?->status === 'completed', 409);
        abort_if($step && in_array($mission->status, ['in_review', 'completed'], true), 409);
        abort_if(! $step && $mission->steps()->where('status', '!=', 'completed')->exists(), 409);
        abort_if($mission->submissions()->where('step_id', $step?->id)->where('status', 'pending')->exists(), 409, 'Există deja o verificare în așteptare.');
        if ($method === 'provider_event') {
            throw ValidationException::withMessages(['method' => 'Acest pas așteaptă confirmarea partenerului.']);
        }
        if ($method === 'document_upload' && empty($input['private_file_key'])) {
            throw ValidationException::withMessages(['evidence' => 'Atașează documentul justificativ.']);
        }
        abort_if(! empty($input['private_file_key']) && $method !== 'document_upload', 422);
        $automatic = $method === 'user_declaration';
        $earning = null;
        if ($step?->confirms_receipt) {
            $previous = $mission->submissions()->where('step_id', $step->id)->whereNotNull('external_earning_id')
                ->with('earning')->latest('id')->first()?->earning;
            if ($previous) {
                abort_if(isset($input['earning_key']) && $input['earning_key'] !== $previous->earning_key, 409);
                $input['earning_key'] = $previous->earning_key;
                $input['amount'] ??= intdiv($previous->amount_minor, 100).'.'.str_pad((string) ($previous->amount_minor % 100), 2, '0', STR_PAD_LEFT);
                $input['currency'] ??= $previous->currency;
                $input['description'] ??= $previous->description;
            }
        }
        if (isset($input['amount'])) {
            abort_unless($step?->confirms_receipt, 422);
            $earning = $this->earning($project, $actor, $input, $automatic ? 'confirmed' : 'pending', $step->id);
        }
        $submission = $mission->submissions()->create([
            'step_id' => $step?->id, 'external_earning_id' => $earning?->id, 'method' => $method,
            'status' => $automatic ? 'approved' : 'pending', 'submitted_by' => $actor->id, 'submitted_at' => now(),
            'evidence_text' => $input['evidence_text'] ?? null, 'private_file_key' => $input['private_file_key'] ?? null,
            'request_key' => $key, 'reviewed_at' => $automatic ? now() : null,
        ]);
        $this->enrollment->event($project, $actor, $step ? 'step_checked' : 'mission_submitted', $mission, $step, null, $submission->status, ['submission_id' => $submission->id]);
        if ($step) {
            $mission->update(['status' => 'in_progress']);
            if ($automatic) {
                $this->approveStep($project, $mission, $step, $actor);
            } else {
                $step->update(['status' => 'in_review']);
            }
        } elseif ($automatic) {
            $this->finish($project, $mission, $actor);
        } else {
            $mission->update(['status' => 'in_review']);
        }
    }

    public function review(MissionSubmission $target, User $actor, string $decision, ?string $note): void
    {
        DB::transaction(function () use ($target, $actor, $decision, $note) {
            $project = Project::lockForUpdate()->findOrFail($target->mission->project_id);
            $mission = $project->missions()->lockForUpdate()->findOrFail($target->mission_id);
            $step = $target->step_id ? $mission->steps()->lockForUpdate()->findOrFail($target->step_id) : null;
            $submission = $mission->submissions()->lockForUpdate()->findOrFail($target->id);
            if ($submission->status !== 'pending') {
                abort_unless($submission->reviewed_by == $actor->id && $submission->status === $decision && $submission->review_note === $note, 409);

                return;
            }
            abort_if($submission->method === 'provider_event', 409);
            $submission->update(['status' => $decision, 'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'review_note' => $note]);
            if ($submission->external_earning_id) {
                $project->earnings()->whereKey($submission->external_earning_id)->where('status', 'pending')->update([
                    'status' => $decision === 'approved' ? 'confirmed' : 'rejected', 'confirmed_at' => $decision === 'approved' ? now() : null,
                ]);
            }
            $this->enrollment->event($project, $actor, 'submission_reviewed', $mission, $step, 'pending', $decision, ['submission_id' => $submission->id, 'note' => $note]);
            if ($decision === 'rejected') {
                ($step ?? $mission)->update(['status' => 'needs_changes']);
            } elseif ($step) {
                $this->approveStep($project, $mission, $step, $actor);
            } else {
                $this->finish($project, $mission, $actor);
            }
        }, 3);
    }

    private function approveStep(Project $project, ProjectMission $mission, ProjectMissionStep $step, User $actor): void
    {
        $from = $step->status;
        $step->update(['status' => 'completed', 'completed_at' => now()]);
        $this->enrollment->event($project, $actor, 'step_approved', $mission, $step, $from, 'completed');
        if ($mission->steps()->where('status', '!=', 'completed')->exists()) {
            return;
        }
        if ($mission->verification_method === 'user_declaration') {
            $this->finish($project, $mission, $actor);

            return;
        }
        $mission->update(['status' => 'in_review']);
        if ($mission->verification_method === 'admin_approval') {
            $mission->submissions()->create(['method' => 'admin_approval', 'status' => 'pending',
                'submitted_at' => now(), 'request_key' => (string) Str::uuid()]);
        }
    }

    private function finish(Project $project, ProjectMission $mission, User $actor): void
    {
        $from = $mission->status;
        $mission->update(['status' => 'completed', 'completed_at' => now()]);
        $this->enrollment->event($project, $actor, 'mission_completed', $mission, null, $from, 'completed');
        if ($project->status === 'active' && ! $project->missions()->where('is_required', true)->where('status', '!=', 'completed')->exists()) {
            $project->update(['status' => 'completed', 'completed_at' => now()]);
            $this->enrollment->event($project, $actor, 'project_completed', null, null, 'active', 'completed');
        }
    }

    public function declareEarning(Project $project, User $actor, array $input): void
    {
        DB::transaction(function () use ($project, $actor, $input) {
            $locked = $actor->projects()->lockForUpdate()->findOrFail($project->id);
            $this->earning($locked, $actor, $input, 'confirmed', null);
        }, 3);
    }

    private function earning(Project $project, User $actor, array $input, string $status, ?int $stepId): ExternalEarning
    {
        [$whole, $fraction] = array_pad(explode('.', $input['amount']), 2, '');
        $amount = (int) $whole * 100 + (int) str_pad($fraction, 2, '0');
        $values = ['amount_minor' => $amount, 'currency' => strtoupper($input['currency']), 'description' => $input['description'] ?? null];
        $earning = $project->earnings()->firstOrNew(['earning_key' => $input['earning_key']]);
        if ($earning->exists) {
            abort_unless($earning->amount_minor == $amount && $earning->currency === $values['currency']
                && $earning->description === $values['description'] && $earning->submissions()->value('step_id') == $stepId, 409);
            if ($earning->status === 'rejected' && $status === 'pending') {
                $earning->update(['status' => 'pending']);
            }

            return $earning;
        }
        $earning->fill([...$values, 'source' => 'user_declaration', 'recorded_by' => $actor->id, 'status' => $status,
            'confirmed_at' => $status === 'confirmed' ? now() : null])->save();
        $this->enrollment->event($project, $actor, 'earning_recorded', metadata: ['earning_id' => $earning->id]);

        return $earning;
    }
}
