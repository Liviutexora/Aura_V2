<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\UserMembership;
use App\Services\MembershipService;
use App\Services\SubscriptionAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubscriptionController extends Controller
{
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
        $trialPlan = ! $user->trial_used_at && ! $hasFutureMembership
            ? Plan::where('is_active', true)->where('trial_days', '>', 0)
                ->orderBy('access_level')->orderBy('id')->first()
            : null;

        return view('subscriptions.index', [
            'demoRoute' => 'subscriptions',
            'currentMembership' => $current,
            'scheduledMembership' => $scheduled,
            'trialPlan' => $trialPlan,
            'remainingDays' => $current ? (int) ceil(now()->diffInDays($current->ends_at)) : null,
        ]);
    }

    public function overview(Request $request, SubscriptionAccess $access): JsonResponse
    {
        $user = $request->user()->refresh();
        $periods = UserMembership::where('user_id', $user->id);

        return response()->json([
            'plans' => Plan::where('is_active', true)->orderBy('access_level')->orderBy('id')->get(),
            'current' => $access->current($user),
            'trial_available' => ! $user->trial_used_at && ! (clone $periods)->where('ends_at', '>', now())->exists()
                && Plan::where('is_active', true)->where('trial_days', '>', 0)->exists(),
            'periods' => $periods->latest('starts_at')->latest('id')->paginate(30),
        ]);
    }

    public function trial(Request $request, MembershipService $service): JsonResponse
    {
        $data = $request->validate(['plan_id' => 'required|integer|min:1', 'request_key' => 'required|uuid']);

        return response()->json($service->trial($request->user(), $data['plan_id'], $data['request_key']), 201);
    }

    public function redeem(Request $request, MembershipService $service): JsonResponse
    {
        $data = $request->validate(['code' => 'required|string|size:40', 'timing' => 'required|in:now,period_end', 'request_key' => 'required|uuid']);

        return response()->json($service->redeem($request->user(), $data['code'], $data['timing'], $data['request_key']), 201);
    }
}
