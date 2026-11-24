<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\SubscriptionEvent;
use App\Models\SubscriptionPromoCode;
use App\Models\User;
use App\Models\UserMembership;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MembershipService
{
    public function trial(User $user, int $planId, string $key): UserMembership
    {
        return DB::transaction(function () use ($user, $planId, $key): UserMembership {
            $owner = $this->lockUser($user);
            $this->requireNewRequest($owner, $key);
            $plan = $this->availablePlan($planId);
            if ($owner->trial_used_at || ! $plan->trial_days || UserMembership::where('user_id', $owner->id)->where('ends_at', '>', now())->exists()) {
                throw ValidationException::withMessages(['trial' => 'Perioada gratuită nu este disponibilă.']);
            }
            $owner->forceFill(['trial_used_at' => now()])->save();

            return $this->grant($owner, $plan, $plan->trial_days, 'trial', 'now', $owner, $key);
        }, 3);
    }

    public function manual(User $admin, User $user, int $planId, int $days, string $timing, string $reason, string $key): UserMembership
    {
        return DB::transaction(function () use ($admin, $user, $planId, $days, $timing, $reason, $key): UserMembership {
            $admin = $this->lockUser($admin);
            abort_unless($admin->isSuperAdmin(), 403);
            abort_if(trim($reason) === '', 422);
            $this->requireNewRequest($admin, $key);

            $recipient = User::lockForUpdate()->findOrFail($user->id);

            return $this->grant($recipient, $this->availablePlan($planId), $days, 'manual', $timing, $admin, $key, $reason);
        }, 3);
    }

    /** @return array{id:int, code:string} */
    public function issueCode(User $admin, int $planId, int $days, ?int $reservedUserId, ?CarbonImmutable $expiresAt, string $key): array
    {
        return DB::transaction(function () use ($admin, $planId, $days, $reservedUserId, $expiresAt, $key): array {
            $admin = $this->lockUser($admin);
            abort_unless($admin->isSuperAdmin(), 403);
            $this->requireNewRequest($admin, $key);
            $plan = $this->availablePlan($planId);
            abort_unless($days > 0 && $days <= 3650 && ($expiresAt === null || $expiresAt->isFuture()), 422);
            $code = bin2hex(random_bytes(20));
            $promo = SubscriptionPromoCode::create(['code_hash' => hash('sha256', $code), 'plan_id' => $plan->id,
                'duration_days' => $days, 'reserved_user_id' => $reservedUserId, 'expires_at' => $expiresAt?->utc(), 'created_by' => $admin->id]);
            SubscriptionEvent::record('promo_issued', $plan->id, $admin, $key, promo: $promo,
                details: $promo->only('reserved_user_id', 'duration_days', 'expires_at'));

            return ['id' => $promo->id, 'code' => $code];
        }, 3);
    }

    public function redeem(User $user, string $code, string $timing, string $key): UserMembership
    {
        return DB::transaction(function () use ($user, $code, $timing, $key): UserMembership {
            $owner = $this->lockUser($user);
            $this->requireNewRequest($owner, $key);
            $promo = SubscriptionPromoCode::where('code_hash', hash('sha256', strtolower(trim($code))))->lockForUpdate()->first();
            if (! $promo || $promo->redeemed_at || $promo->expires_at?->lessThanOrEqualTo(now())
                || ($promo->reserved_user_id !== null && $promo->reserved_user_id !== $owner->id)) {
                throw ValidationException::withMessages(['code' => 'Cod indisponibil.']);
            }
            $membership = $this->grant($owner, $this->availablePlan($promo->plan_id), $promo->duration_days, 'promo', $timing, $owner, $key, promo: $promo);
            $promo->update(['membership_id' => $membership->id, 'redeemed_at' => now()]);

            return $membership;
        }, 3);
    }

    private function lockUser(User $user): User
    {
        $locked = User::lockForUpdate()->findOrFail($user->id);
        abort_unless($locked->isActive() && $locked->hasVerifiedEmail(), 403);

        return $locked;
    }

    private function requireNewRequest(User $actor, string $key): void
    {
        abort_if(SubscriptionEvent::where('actor_id', $actor->id)->where('request_key', $key)->lockForUpdate()->first(), 409, 'Operațiune deja procesată.');
    }

    private function availablePlan(int $id): Plan
    {
        $plan = Plan::sharedLock()->findOrFail($id);
        abort_unless($plan->is_active, 409, 'Plan inactiv.');

        return $plan;
    }

    private function grant(User $owner, Plan $plan, int $days, string $source, string $timing, User $actor, string $key,
        ?string $reason = null, ?SubscriptionPromoCode $promo = null): UserMembership
    {
        abort_unless($days > 0 && $days <= 3650 && in_array($timing, ['now', 'period_end'], true), 422);
        $now = CarbonImmutable::now()->startOfSecond();
        $periods = UserMembership::where('user_id', $owner->id)->where('ends_at', '>', $now)
            ->orderBy('starts_at')->lockForUpdate()->get();
        $current = $periods->first(fn (UserMembership $period): bool => $period->starts_at->lessThanOrEqualTo($now));
        $start = $now;
        $operation = 'activated';

        if ($current) {
            $upgrade = $plan->id !== $current->plan_id && $plan->access_level > $current->terms['access_level'];
            if ($upgrade && $timing === 'now') {
                $oldEnd = $current->ends_at;
                $current->update(['ends_at' => $now, 'closed_at' => $now]);
                SubscriptionEvent::record('replaced', $current->plan_id, $actor, membership: $current, reason: $reason,
                    details: ['previous_end' => $oldEnd, 'effective_end' => $now]);
                $operation = 'upgraded';
            } else {
                $start = $current->ends_at;
                $operation = $current->plan_id === $plan->id ? 'extended' : 'change_scheduled';
            }
        }

        $membership = UserMembership::create(['user_id' => $owner->id, 'plan_id' => $plan->id, 'terms' => $plan->terms(),
            'source' => $source, 'starts_at' => $start, 'ends_at' => $start->addDays($days)]);
        SubscriptionEvent::record($operation, $plan->id, $actor, $key, $membership, $promo, $reason,
            ['duration_days' => $days, 'starts_at' => $start, 'ends_at' => $membership->ends_at, 'plan_version' => $plan->version]);

        $nextStart = $membership->ends_at;
        foreach ($periods as $scheduled) {
            if ($scheduled->starts_at->lessThanOrEqualTo($now)) {
                continue;
            }
            $previous = $scheduled->only('starts_at', 'ends_at');
            $seconds = $scheduled->ends_at->getTimestamp() - $scheduled->starts_at->getTimestamp();
            $scheduled->fill(['starts_at' => $nextStart, 'ends_at' => $nextStart->addSeconds($seconds)]);
            if ($scheduled->isDirty()) {
                $scheduled->save();
                SubscriptionEvent::record('rescheduled', $scheduled->plan_id, $actor, membership: $scheduled, reason: $reason,
                    details: ['before' => $previous, 'after' => $scheduled->only('starts_at', 'ends_at'), 'trigger_membership_id' => $membership->id]);
            }
            $nextStart = $scheduled->ends_at;
        }

        return $membership;
    }

    public function closeExpired(): void
    {
        UserMembership::whereNull('closed_at')->where('ends_at', '<=', now())->select('id')
            ->chunkById(100, function ($candidates): void {
                foreach ($candidates as $candidate) {
                    DB::transaction(function () use ($candidate): void {
                        $period = UserMembership::lockForUpdate()->findOrFail($candidate->id);
                        if (! $period->closed_at && $period->ends_at->lessThanOrEqualTo(now())) {
                            $period->update(['closed_at' => $period->ends_at]);
                            SubscriptionEvent::record('expired', $period->plan_id, membership: $period,
                                details: ['effective_at' => $period->ends_at]);
                        }
                    }, 3);
                }
            });
    }
}
