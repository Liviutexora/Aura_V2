<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionEvent extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

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
