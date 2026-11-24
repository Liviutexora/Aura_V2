<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserMembership extends Model
{
    protected $guarded = ['id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return ['starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime', 'closed_at' => 'immutable_datetime', 'terms' => 'array'];
    }

    public function scopeEffectiveAt(Builder $query, DateTimeInterface $at): Builder
    {
        return $query->where('starts_at', '<=', $at)->where('ends_at', '>', $at);
    }
}
