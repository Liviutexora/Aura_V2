<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserMembership extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::addGlobalScope('exclude_cancelled', function (Builder $query): void {
            // A period closed before its scheduled start was cancelled without taking effect.
            $query->where(fn (Builder $periods) => $periods->whereNull('user_memberships.closed_at')
                ->orWhereColumn('user_memberships.closed_at', '>=', 'user_memberships.starts_at'));
        });
    }

    public function scopeWithCancelled(Builder $query): Builder
    {
        return $query->withoutGlobalScope('exclude_cancelled');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sourceLabel(): string
    {
        return match ($this->source) {
            'trial' => 'Trial',
            'manual' => 'ADMIN',
            'promo' => 'Promoție',
            'stripe' => 'Stripe',
            default => $this->source,
        };
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
