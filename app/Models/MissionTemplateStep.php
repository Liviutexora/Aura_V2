<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MissionTemplateStep extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'deadline_on' => 'immutable_date',
            'confirms_receipt' => 'boolean',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(MissionTemplate::class, 'template_id');
    }

    public function anchorStep(): BelongsTo
    {
        return $this->belongsTo(MissionTemplateStep::class, 'anchor_step_id');
    }
}
