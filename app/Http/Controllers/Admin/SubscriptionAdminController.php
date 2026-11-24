<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\SubscriptionEvent;
use App\Models\User;
use App\Models\UserMembership;
use App\Services\MembershipService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SubscriptionAdminController extends Controller
{
    public function index(): View
    {
        return view('admin.pages.plans', [
            'plans' => Plan::orderBy('access_level')->orderBy('id')->get(),
            'demoData' => ['nav' => json_decode(file_get_contents(resource_path('data/admin-navigation.json')), true, 512, JSON_THROW_ON_ERROR)],
            'demoPage' => 'plans',
            'pageTitle' => 'Planuri de abonament',
            'auraAdminServerRendered' => true,
        ]);
    }

    public function plans(): JsonResponse
    {
        return response()->json(Plan::orderBy('access_level')->orderBy('id')->paginate(50));
    }

    public function savePlan(Request $request, ?Plan $plan = null): JsonResponse
    {
        $required = $plan ? 'sometimes' : 'required';
        $limit = [$plan ? 'sometimes' : 'present', 'nullable', 'integer', 'between:0,1000000'];
        $data = $request->validate([
            'name' => [$required, 'filled', 'string', 'max:120'],
            'is_active' => [$required, 'boolean'],
            'benefits' => [$plan ? 'sometimes' : 'present', 'array', 'list', 'max:50'],
            'benefits.*' => ['required', 'string', 'max:500'],
            'access_level' => [$required, 'integer', 'between:1,1000000'],
            'max_projects' => $limit, 'max_explorer_projects' => $limit, 'max_mentor_projects' => $limit,
            'trial_days' => [$required, 'integer', 'between:0,3650'],
            'monthly_price_minor' => [$required, 'integer', 'between:0,2147483647'],
            'quarterly_price_minor' => [$required, 'integer', 'between:0,2147483647'],
            'version' => [$plan ? 'required' : 'prohibited', 'integer', 'min:1'],
            'request_key' => 'required|uuid',
        ]);

        return DB::transaction(function () use ($plan, $data, $request): JsonResponse {
            $admin = User::lockForUpdate()->findOrFail($request->user()->id);
            abort_unless($admin->isSuperAdmin() && $admin->hasVerifiedEmail(), 403);
            $key = $data['request_key'];
            abort_if(SubscriptionEvent::where('actor_id', $admin->id)->where('request_key', $key)->exists(), 409);
            $record = $plan ? Plan::lockForUpdate()->findOrFail($plan->id) : new Plan;
            abort_if($plan && $record->version !== (int) $data['version'], 409);
            $before = $plan ? ($record->terms() + ['is_active' => $record->is_active]) : null;
            unset($data['request_key'], $data['version']);
            $record->fill($data);
            $record->version = $plan ? $record->version + 1 : 1;
            $record->save();
            $record->refresh();
            SubscriptionEvent::record($plan ? 'plan_updated' : 'plan_created', $record->id, $admin, $key,
                details: ['before' => $before, 'after' => $record->terms() + ['is_active' => $record->is_active]]);

            return response()->json($record, $plan ? 200 : 201);
        }, 3);
    }

    public function memberships(): JsonResponse
    {
        return response()->json(UserMembership::latest('id')->paginate(50));
    }

    public function events(): JsonResponse
    {
        return response()->json(SubscriptionEvent::latest('id')->paginate(50));
    }

    public function grant(Request $request, User $user, MembershipService $service): JsonResponse
    {
        $data = $request->validate(['plan_id' => 'required|integer|min:1', 'duration_days' => 'required|integer|between:1,3650',
            'timing' => 'required|in:now,period_end', 'reason' => 'required|string|max:1000', 'request_key' => 'required|uuid']);

        return response()->json($service->manual($request->user(), $user, $data['plan_id'], $data['duration_days'],
            $data['timing'], $data['reason'], $data['request_key']), 201);
    }

    public function issueCode(Request $request, MembershipService $service): JsonResponse
    {
        $data = $request->validate(['plan_id' => 'required|integer|min:1', 'duration_days' => 'required|integer|between:1,3650',
            'reserved_user_id' => 'nullable|integer|exists:users,id', 'expires_at' => 'nullable|date|after:now', 'request_key' => 'required|uuid']);

        return response()->json($service->issueCode($request->user(), $data['plan_id'], $data['duration_days'],
            $data['reserved_user_id'] ?? null, isset($data['expires_at']) ? CarbonImmutable::parse($data['expires_at']) : null,
            $data['request_key']), 201, ['Cache-Control' => 'no-store']);
    }
}
