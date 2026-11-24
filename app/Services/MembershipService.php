<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\SubscriptionEvent;
use App\Models\SubscriptionPromoCode;
use App\Models\User;
use App\Models\UserMembership;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
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

    public function manual(User $admin, User $user, int $planId, int $days, string $timing, string $reason, string $key,
        ?int $confirmedReplacementId = null): UserMembership
    {
        return DB::transaction(function () use ($admin, $user, $planId, $days, $timing, $reason, $key, $confirmedReplacementId): UserMembership {
            $admin = $this->lockUser($admin);
            abort_unless($admin->isSuperAdmin(), 403);
            abort_if(trim($reason) === '', 422);
            $this->requireNewRequest($admin, $key);

            $recipient = User::lockForUpdate()->findOrFail($user->id);

            return $this->grant($recipient, $this->availablePlan($planId), $days, 'manual', $timing, $admin, $key, $reason,
                confirmedReplacementId: $confirmedReplacementId);
        }, 3);
    }

    public function cancelScheduled(User $admin, int $periodId, int $startsAt, int $endsAt, string $key): void
    {
        DB::transaction(function () use ($admin, $periodId, $startsAt, $endsAt, $key): void {
            $admin = $this->lockUser($admin);
            abort_unless($admin->isSuperAdmin(), 403);
            $this->requireNewRequest($admin, $key);

            $ownerId = UserMembership::withCancelled()->findOrFail($periodId, ['user_id'])->user_id;
            User::lockForUpdate()->findOrFail($ownerId);
            $period = UserMembership::withCancelled()->lockForUpdate()->findOrFail($periodId);
            $now = CarbonImmutable::now()->startOfSecond();
            if ($period->closed_at !== null || $period->starts_at->lessThanOrEqualTo($now)) {
                throw ValidationException::withMessages(['period' => 'Perioada nu mai este programată.']);
            }
            if ($period->starts_at->timestamp !== $startsAt || $period->ends_at->timestamp !== $endsAt) {
                throw ValidationException::withMessages(['period' => 'Datele perioadei s-au schimbat. Verifică noua programare și confirmă din nou.']);
            }
            $later = UserMembership::where('user_id', $ownerId)->whereKeyNot($periodId)
                ->whereBetween('starts_at', [$period->starts_at, $period->ends_at])
                ->orderBy('starts_at')->lockForUpdate()->first();
            if ($later) {
                throw ValidationException::withMessages(['period' => 'Perioada #'.$later->id.' depinde de cea selectată. Anulează mai întâi perioada ulterioară.']);
            }

            $period->update(['closed_at' => $now]);
            SubscriptionEvent::record('cancelled', $period->plan_id, $admin, $key, membership: $period,
                details: $period->only('starts_at', 'ends_at', 'closed_at'));
        }, 3);
    }

    /** @return array{id:int, code:string} */
    public function issueCode(User $admin, int $planId, int $days, ?int $reservedUserId, ?CarbonImmutable $expiresAt, string $key, ?string $reason = null): array
    {
        abort_unless($expiresAt !== null, 422);

        return $this->issueCodes($admin, $planId, $days, 1, $reservedUserId, $expiresAt, $key, $reason)[0];
    }

    /** @return array<int, array{id:int, code:string}> */
    public function issueCodes(User $admin, int $planId, int $days, int $quantity, ?int $reservedUserId,
        CarbonImmutable $expiresAt, string $key, ?string $reason = null): array
    {
        return DB::transaction(function () use ($admin, $planId, $days, $quantity, $reservedUserId, $expiresAt, $key, $reason): array {
            $admin = $this->lockUser($admin);
            abort_unless($admin->isSuperAdmin(), 403);
            $this->requireNewRequest($admin, $key);
            $plan = $this->availablePlan($planId);
            abort_unless($days > 0 && $days <= 3650 && $quantity > 0 && $quantity <= 100 && $expiresAt->isFuture(), 422);
            $issued = [];
            $generatedHashes = [];

            for ($index = 0; $index < $quantity; $index++) {
                [$code, $hash] = $this->uniquePromoCode($generatedHashes);
                $generatedHashes[$hash] = true;
                $promo = SubscriptionPromoCode::create([
                    'code_hash' => $hash,
                    'encrypted_code' => $code,
                    'plan_id' => $plan->id,
                    'duration_days' => $days,
                    'reserved_user_id' => $reservedUserId,
                    'expires_at' => $expiresAt->utc(),
                    'created_by' => $admin->id,
                ]);
                SubscriptionEvent::record('promo_issued', $plan->id, $admin, $index === 0 ? $key : null,
                    promo: $promo, reason: $reason, details: [
                        'reserved_user_id' => $promo->reserved_user_id,
                        'duration_days' => $promo->duration_days,
                        'expires_at' => $promo->expires_at,
                        'batch_request_key' => $key,
                        'batch_index' => $index + 1,
                        'batch_size' => $quantity,
                    ]);
                $issued[] = ['id' => $promo->id, 'code' => $code];
            }

            return $issued;
        }, 3);
    }

    public function revokeCode(User $admin, SubscriptionPromoCode $promoCode, string $key): SubscriptionPromoCode
    {
        return DB::transaction(function () use ($admin, $promoCode, $key): SubscriptionPromoCode {
            $admin = $this->lockUser($admin);
            abort_unless($admin->isSuperAdmin(), 403);
            $this->requireNewRequest($admin, $key);
            $promo = SubscriptionPromoCode::lockForUpdate()->findOrFail($promoCode->id);
            if ($promo->redeemed_at !== null) {
                throw ValidationException::withMessages(['promo_code' => 'Un cod utilizat nu poate fi revocat.']);
            }
            if ($promo->revoked_at !== null) {
                throw ValidationException::withMessages(['promo_code' => 'Codul promoțional este deja revocat.']);
            }
            $promo->update(['revoked_at' => now(), 'revoked_by' => $admin->id]);
            SubscriptionEvent::record('promo_revoked', $promo->plan_id, $admin, $key, promo: $promo,
                details: ['revoked_at' => $promo->revoked_at]);

            return $promo;
        }, 3);
    }

    public function deleteCode(User $admin, int $promoCodeId, string $key): void
    {
        DB::transaction(function () use ($admin, $promoCodeId, $key): void {
            $admin = $this->lockUser($admin);
            abort_unless($admin->isSuperAdmin(), 403);
            $this->requireNewRequest($admin, $key);
            $promo = SubscriptionPromoCode::lockForUpdate()->findOrFail($promoCodeId);
            $events = SubscriptionEvent::where('promo_code_id', $promo->id)->lockForUpdate()->get();

            if ($promo->redeemed_at !== null || $promo->membership_id !== null || $events->contains(fn (SubscriptionEvent $event): bool => $event->membership_id !== null)) {
                throw ValidationException::withMessages(['promo_code' => 'Cuponul a fost utilizat sau este asociat unui abonament.']);
            }

            foreach ($events as $event) {
                if (! in_array($event->type, ['promo_issued', 'promo_revoked'], true)) {
                    throw ValidationException::withMessages(['promo_code' => 'Cuponul are o referință de audit care nu poate fi desprinsă în siguranță.']);
                }
                $event->update(['promo_code_id' => null, 'details' => array_merge($event->details, [
                    'deleted_promo_code_id' => $promo->id,
                    'reserved_user_id' => $promo->reserved_user_id,
                ])]);
            }

            SubscriptionEvent::record('promo_deleted', $promo->plan_id, $admin, $key, details: [
                'deleted_promo_code_id' => $promo->id,
                'reserved_user_id' => $promo->reserved_user_id,
            ]);

            try {
                $promo->delete();
            } catch (QueryException $exception) {
                if (in_array((int) ($exception->errorInfo[1] ?? 0), [1451, 787], true)
                    || str_contains($exception->errorInfo[2] ?? '', 'FOREIGN KEY constraint failed')) {
                    throw ValidationException::withMessages(['promo_code' => 'Cuponul are referințe în baza de date care împiedică ștergerea.']);
                }
                throw $exception;
            }
        }, 3);
    }

    public function redeem(User $user, #[\SensitiveParameter] string $code, int $planId, string $timing, string $key,
        ?int $confirmedReplacementId = null, ?int $confirmedEnd = null): UserMembership
    {
        return DB::transaction(function () use ($user, $code, $planId, $timing, $key, $confirmedReplacementId, $confirmedEnd): UserMembership {
            $owner = $this->lockUser($user);
            $this->requireNewRequest($owner, $key);
            $promo = SubscriptionPromoCode::where('code_hash', hash('sha256', strtolower(trim($code))))->lockForUpdate()->first();
            if (! $promo || $promo->plan_id !== $planId || $promo->membership_id || $promo->redeemed_at || $promo->revoked_at || $promo->expires_at?->lessThanOrEqualTo(now())
                || ($promo->reserved_user_id !== null && $promo->reserved_user_id !== $owner->id)) {
                throw ValidationException::withMessages(['code' => 'Cod indisponibil.']);
            }
            $membership = $this->grant($owner, $this->availablePlan($planId), $promo->duration_days, 'promo', $timing, $owner, $key,
                promo: $promo, confirmedReplacementId: $confirmedReplacementId, confirmedEnd: $confirmedEnd);
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

    /**
     * @param  array<string, bool>  $generatedHashes
     * @return array{string, string}
     */
    private function uniquePromoCode(array $generatedHashes): array
    {
        $alphabet = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';

        for ($attempt = 0; $attempt < 20; $attempt++) {
            $code = '';
            for ($position = 0; $position < 9; $position++) {
                $code .= $alphabet[random_int(0, 35)];
            }
            $hash = hash('sha256', strtolower($code));
            if (! isset($generatedHashes[$hash]) && ! SubscriptionPromoCode::where('code_hash', $hash)->exists()) {
                return [$code, $hash];
            }
        }

        throw new \RuntimeException('Nu a putut fi generat un cod promoțional unic.');
    }

    private function grant(User $owner, Plan $plan, int $days, string $source, string $timing, User $actor, string $key,
        ?string $reason = null, ?SubscriptionPromoCode $promo = null, ?int $confirmedReplacementId = null, ?int $confirmedEnd = null): UserMembership
    {
        abort_unless($days > 0 && $days <= 3650 && in_array($timing, ['now', 'period_end'], true), 422);
        $now = CarbonImmutable::now()->startOfSecond();
        $periods = UserMembership::where('user_id', $owner->id)->where('ends_at', '>', $now)
            ->orderBy('starts_at')->lockForUpdate()->get();
        $current = $periods->first(fn (UserMembership $period): bool => $period->starts_at->lessThanOrEqualTo($now));
        $start = $now;
        $operation = 'activated';

        if ($promo && $periods->contains(fn (UserMembership $period): bool => $period->starts_at->greaterThan($now))) {
            throw ValidationException::withMessages(['timing' => 'Ai deja o perioadă programată. Cuponul nu a fost consumat.']);
        }
        if ($confirmedReplacementId !== null && $current?->id !== $confirmedReplacementId) {
            throw ValidationException::withMessages(['confirm_replace_membership_id' => 'Perioada activă s-a schimbat. Verifică din nou abonamentul înainte de confirmare.']);
        }

        if ($current) {
            $changingPlan = $plan->id !== $current->plan_id;
            $higherLevel = $plan->access_level > $current->terms['access_level'];
            if ($changingPlan && $timing === 'now') {
                if ($confirmedReplacementId === null || ($promo && $confirmedEnd !== $current->ends_at->getTimestamp())) {
                    throw ValidationException::withMessages(['confirm_replace_membership_id' => 'Confirmă explicit pierderea perioadei rămase pentru schimbarea imediată.']);
                }
                $oldEnd = $current->ends_at;
                $current->update(['ends_at' => $now, 'closed_at' => $now]);
                SubscriptionEvent::record('replaced', $current->plan_id, $actor, membership: $current, reason: $reason,
                    details: ['previous_end' => $oldEnd, 'effective_end' => $now]);
                $operation = $higherLevel ? 'upgraded' : 'plan_changed';
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
