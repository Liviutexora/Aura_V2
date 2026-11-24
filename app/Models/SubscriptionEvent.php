<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionEvent extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(UserMembership::class)->withCancelled();
    }

    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPromoCode::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function scopeAuditHistory(Builder $query, ?User $user = null): Builder
    {
        return $query->with(['actor:id,name,email', 'membership.user:id,name,email',
            'promoCode.reservedUser:id,name,email', 'plan:id,name'])
            ->when($user, fn (Builder $events) => $events->where(fn (Builder $events) => $events
                ->whereRelation('membership', 'user_id', $user->id)
                ->orWhereRelation('promoCode', 'reserved_user_id', $user->id)
                ->orWhere(fn (Builder $events) => $events->whereNotNull('details->deleted_promo_code_id')
                    ->where('details->reserved_user_id', $user->id))))
            ->orderByDesc('occurred_at')->orderByDesc('id');
    }

    public function operationLabel(): string
    {
        return match ($this->type) {
            'activated' => 'Activare',
            'extended' => 'Prelungire',
            'upgraded' => 'Upgrade imediat',
            'plan_changed' => 'Schimbare imediată',
            'change_scheduled' => 'Schimbare programată',
            'replaced' => 'Înlocuire perioadă activă',
            'rescheduled' => 'Reprogramare perioadă',
            'cancelled' => 'Anulare perioadă programată',
            'expired' => 'Expirare',
            'promo_issued' => 'Generare cod promoțional',
            'promo_revoked' => 'Revocare cod promoțional',
            'promo_deleted' => 'Ștergere definitivă cod promoțional',
            'plan_created' => 'Creare plan',
            'plan_updated' => 'Editare plan',
            default => $this->type,
        };
    }

    protected function casts(): array
    {
        return ['details' => 'array', 'occurred_at' => 'immutable_datetime'];
    }

    /** @param array<string,mixed> $details */
    public static function record(string $type, int $planId, ?User $actor = null, ?string $key = null,
        ?UserMembership $membership = null, ?SubscriptionPromoCode $promo = null, ?string $reason = null, array $details = []): self
    {
        return self::create(['type' => $type, 'plan_id' => $planId, 'actor_id' => $actor?->id,
            'request_key' => $key, 'membership_id' => $membership?->id, 'promo_code_id' => $promo?->id,
            'reason' => $reason, 'details' => $details, 'occurred_at' => now()]);
    }
}
