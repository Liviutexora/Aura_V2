<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionPromoCode extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['code_hash', 'encrypted_code'];

    public function reservedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reserved_user_id');
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(UserMembership::class)->withCancelled();
    }

    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function scopeInventory(Builder $query, ?string $status = null): Builder
    {
        $query->select(['id', 'encrypted_code', 'plan_id', 'duration_days', 'expires_at', 'reserved_user_id',
            'membership_id', 'redeemed_at', 'revoked_at', 'revoked_by', 'created_by', 'created_at', 'updated_at']);

        return match ($status) {
            'arhiva' => $query->where(fn (Builder $archived) => $archived->whereNotNull('redeemed_at')
                ->orWhereNotNull('revoked_at')->orWhere('expires_at', '<=', now())),
            'utilizat' => $query->whereNotNull('redeemed_at'),
            'revocat' => $query->whereNull('redeemed_at')->whereNotNull('revoked_at'),
            'expirat' => $query->whereNull('redeemed_at')->whereNull('revoked_at')->where('expires_at', '<=', now()),
            'disponibil' => $query->whereNull('redeemed_at')->whereNull('revoked_at')
                ->where(fn (Builder $expiry) => $expiry->whereNull('expires_at')->orWhere('expires_at', '>', now())),
            default => $query,
        };
    }

    protected function status(): Attribute
    {
        return Attribute::get(fn (): string => match (true) {
            $this->redeemed_at !== null => 'utilizat',
            $this->revoked_at !== null => 'revocat',
            $this->expires_at?->lessThanOrEqualTo(now()) === true => 'expirat',
            default => 'disponibil',
        });
    }

    protected function casts(): array
    {
        return [
            'encrypted_code' => 'encrypted',
            'expires_at' => 'immutable_datetime',
            'redeemed_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }
}
