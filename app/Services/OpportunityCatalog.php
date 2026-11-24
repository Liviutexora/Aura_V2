<?php

namespace App\Services;

use App\Models\EligibilityRule;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class OpportunityCatalog
{
    public const TIMEZONE = 'Europe/Bucharest';

    public function published(bool $expired = false, ?int $maxAccessLevel = null): Builder
    {
        $query = Opportunity::query()->with('category')->where('status', 'published')
            ->when($maxAccessLevel !== null, fn (Builder $query) => $query->where('min_access_level', '<=', $maxAccessLevel))
            ->whereHas('category', fn (Builder $category) => $category->where('status', 'active')->where('visibility', 'all'))
            ->where('starts_at', '<=', now());

        if ($expired) {
            return $query->where('ends_at', '<=', now());
        }

        return $query->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->where(fn (Builder $q) => $q->whereNull('participation_limit')
                ->orWhereRaw('(select count(*) from projects where projects.opportunity_id = opportunities.id) < participation_limit'));
    }

    public function rules(Opportunity $offer): Collection
    {
        return EligibilityRule::where('category_id', $offer->category_id)->where('status', 'active')->where('visibility', 'all')
            ->where(fn (Builder $q) => $q->whereNull('opportunity_id')->orWhere('opportunity_id', $offer->id))
            ->orderBy('id')->get();
    }

    public function snapshot(Opportunity $offer, User $owner, User $actor, array $attestations): array
    {
        $result = [];
        foreach ($this->rules($offer) as $rule) {
            $eligible = match ($rule->check_method) {
                'user_attestation' => $owner->is($actor) && in_array($rule->id.':'.$rule->version, $attestations, true),
                'partner_review' => $offer->templates()->where('status', 'active')->where('is_required', true)->where('assign_on_accept', true)
                    ->whereHas('steps', fn (Builder $q) => $q->where('status', 'active')->whereIn('verification_method', ['admin_approval', 'provider_event']))->exists(),
                default => match ($rule->criterion_code) {
                    'active_account' => $owner->isActive(),
                    'verified_email' => $owner->email_verified_at !== null,
                    default => false,
                },
            };
            if (! $eligible) {
                throw ValidationException::withMessages(['eligibility' => 'Condiție neîndeplinită: '.$rule->name]);
            }
            $result[] = [...$rule->only('id', 'version', 'name', 'conditions_text', 'check_method', 'criterion_code', 'criterion_params'), 'result' => $rule->check_method === 'partner_review' ? 'awaiting_review' : 'accepted', 'actor' => $actor->id, 'at' => now()->toISOString()];
        }

        return $result;
    }
}
