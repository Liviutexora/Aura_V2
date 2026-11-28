<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\UserMembership;
use App\Services\MembershipService;
use App\Services\SubscriptionAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SubscriptionController extends Controller
{
    public function select(Request $request, Plan $plan): RedirectResponse
    {
        abort_unless($plan->is_active, 404);
        $request->session()->put('subscriptions.selected_plan', $plan->id);
        if (! $request->user()) {
            redirect()->setIntendedUrl(route('aura.subscriptions'));

            return redirect()->route('aura.register');
        }

        return redirect()->route('aura.subscriptions');
    }

    public function show(Request $request, SubscriptionAccess $access): View
    {
        $user = $request->user()->refresh();
        $current = $access->current($user);
        $scheduled = UserMembership::whereBelongsTo($user)
            ->where('starts_at', '>', now())
            ->orderBy('starts_at')
            ->orderBy('id')
            ->first();
        $hasFutureMembership = $current !== null || $scheduled !== null;
        $onboarding = (bool) $request->session()->get('subscriptions.onboarding') && ! $hasFutureMembership;
        $activePlans = Plan::activeCatalog();
        $selectedPlanId = $request->session()->get('subscriptions.selected_plan');
        $selectedPlan = $activePlans->firstWhere('id', $selectedPlanId);
        $trialEligible = ! $user->trial_used_at && ! $hasFutureMembership;
        $trialPlan = $trialEligible && ! $onboarding
            ? Plan::where('is_active', true)->where('trial_days', '>', 0)
                ->orderBy('access_level')->orderBy('id')->first()
            : null;

        return view('subscriptions.index', [
            'demoRoute' => 'subscriptions',
            'activePlans' => $activePlans,
            'selectedPlanId' => $selectedPlan?->id,
            'selectedPlan' => $selectedPlan,
            'onboarding' => $onboarding,
            'trialEligible' => $trialEligible,
            'selectionUnavailable' => $selectedPlanId !== null && $selectedPlan === null,
            'currentMembership' => $current,
            'scheduledMembership' => $scheduled,
            'trialPlan' => $trialPlan,
            'remainingDays' => $current ? (int) ceil(now()->diffInDays($current->ends_at)) : null,
        ]);
    }

    public function choose(Request $request): JsonResponse
    {
        $data = $request->validate(['plan_id' => ['required', 'integer', 'min:1']]);
        $plan = Plan::whereKey($data['plan_id'])->where('is_active', true)->firstOrFail();
        $request->session()->put('subscriptions.selected_plan', $plan->id);

        return response()->json(['plan_id' => $plan->id]);
    }

    public function overview(Request $request, SubscriptionAccess $access): JsonResponse
    {
        $user = $request->user()->refresh();
        $periods = UserMembership::where('user_id', $user->id);

        return response()->json([
            'plans' => Plan::activeCatalog(),
            'current' => $access->current($user),
            'trial_available' => ! $user->trial_used_at && ! (clone $periods)->where('ends_at', '>', now())->exists()
                && Plan::where('is_active', true)->where('trial_days', '>', 0)->exists(),
            'periods' => $periods->latest('starts_at')->latest('id')->paginate(30),
        ]);
    }

    public function trial(Request $request, MembershipService $service): JsonResponse
    {
        $data = $request->validate(['plan_id' => 'required|integer|min:1', 'request_key' => 'required|uuid']);
        $this->requireSelectedPlan($request, $data['plan_id']);

        $membership = $service->trial($request->user(), $data['plan_id'], $data['request_key']);
        $this->finishOnboarding($request);

        return response()->json($membership, 201);
    }

    public function redeem(Request $request, MembershipService $service): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'regex:/\A(?:[A-Za-z0-9]{9}|[A-Fa-f0-9]{40})\z/'],
            'plan_id' => ['required', 'integer', 'min:1'],
            'interval' => ['sometimes', 'required', 'in:monthly,quarterly'],
            'method' => ['sometimes', 'in:coupon'],
            'confirm_replace_membership_id' => ['nullable', 'integer', 'min:1', 'prohibited_unless:timing,now'],
            'confirm_replace_ends_at' => ['nullable', 'integer', 'min:1'],
            'timing' => ['required', 'in:now,period_end'],
            'request_key' => ['required', 'uuid'],
        ]);
        $this->requireSelectedPlan($request, $data['plan_id']);

        $membership = $service->redeem($request->user(), $data['code'], $data['plan_id'], $data['timing'], $data['request_key'],
            $data['confirm_replace_membership_id'] ?? null, $data['confirm_replace_ends_at'] ?? null);
        $this->finishOnboarding($request);

        return response()->json($membership, 201);
    }

    private function finishOnboarding(Request $request): void
    {
        if ($request->session()->get('subscriptions.onboarding')) {
            $request->session()->forget('subscriptions.onboarding');
        }
        $request->session()->forget('subscriptions.selected_plan');
    }

    private function requireSelectedPlan(Request $request, int $planId): void
    {
        if ($request->session()->get('subscriptions.onboarding') && (int) $request->session()->get('subscriptions.selected_plan') !== $planId) {
            throw ValidationException::withMessages(['plan_id' => 'Selectează planul înainte de activare.']);
        }
    }
}
