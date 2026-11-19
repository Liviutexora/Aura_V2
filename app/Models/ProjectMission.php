<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ProjectMission extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'due_on' => 'immutable_date',
            'assigned_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(MissionTemplate::class, 'template_id');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(ProjectMissionStep::class, 'mission_id')->orderBy('position');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(MissionSubmission::class, 'mission_id');
    }

    public function reminder(): HasOne
    {
        return $this->hasOne(MissionReminder::class, 'mission_id');
    }

    public function anchorStep(): BelongsTo
    {
        return $this->belongsTo(ProjectMissionStep::class, 'anchor_step_id');
    }

    public function progress(): int
    {
        $steps = $this->steps;

        return $steps->isEmpty() ? 0 : (int) round(100 * $steps->where('status', 'completed')->count() / $steps->count());
    }

    public function effectiveDueOn(): ?CarbonImmutable
    {
        if ($this->due_on || $this->deadline_anchor) {
            return $this->due_on;
        }

        return $this->project->due_on;
    }
}
