<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MissionTemplate extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'deadline_on' => 'immutable_date',
            'assign_on_accept' => 'boolean',
            'is_required' => 'boolean',
        ];
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(MissionTemplateStep::class, 'template_id')->orderBy('position');
    }

    public function anchorStep(): BelongsTo
    {
        return $this->belongsTo(MissionTemplateStep::class, 'anchor_step_id');
    }
}
