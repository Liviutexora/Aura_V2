<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectMissionStep extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'confirms_receipt' => 'boolean',
            'due_on' => 'immutable_date',
            'completed_at' => 'immutable_datetime',
        ];
    }

    public function mission(): BelongsTo
    {
        return $this->belongsTo(ProjectMission::class, 'mission_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(MissionSubmission::class, 'step_id');
    }

    public function sourceStep(): BelongsTo
    {
        return $this->belongsTo(MissionTemplateStep::class, 'source_step_id');
    }

    public function anchorStep(): BelongsTo
    {
        return $this->belongsTo(ProjectMissionStep::class, 'anchor_step_id');
    }

    public function effectiveDueOn(): ?CarbonImmutable
    {
        if ($this->due_on || $this->deadline_anchor) {
            return $this->due_on;
        }

        return $this->mission->effectiveDueOn();
    }
}
