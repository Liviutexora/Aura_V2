<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MissionReminder extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'remind_at' => 'immutable_datetime',
            'sent_at' => 'immutable_datetime',
        ];
    }

    public function mission(): BelongsTo
    {
        return $this->belongsTo(ProjectMission::class, 'mission_id');
    }
}
