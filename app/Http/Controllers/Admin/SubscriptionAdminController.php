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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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

    public function form(?Plan $plan = null): View
    {
        return view('admin.pages.record', [
            'plan' => $plan ?? new Plan,
            'realPlanForm' => true,
            'demoData' => ['nav' => json_decode(file_get_contents(resource_path('data/admin-navigation.json')), true, 512, JSON_THROW_ON_ERROR)],
            'demoPage' => $plan ? 'plans/'.$plan->id.'/edit' : 'plans/create',
            'pageTitle' => 'Planuri de abonament',
            'auraAdminServerRendered' => true,
        ]);
    }

    private function preparePlanForm(Request $request): void
    {
        $rules = [
            'monthly_price' => ['required', 'string', 'regex:/\A[0-9]{1,8}(?:[.,][0-9]{1,2})?\z/'],
            'quarterly_price' => ['required', 'string', 'regex:/\A[0-9]{1,8}(?:[.,][0-9]{1,2})?\z/'],
            'benefits_text' => ['present', 'nullable', 'string'],
        ];
        foreach (['max_projects', 'max_explorer_projects', 'max_mentor_projects'] as $field) {
            $rules[$field.'_unlimited'] = ['required', 'boolean'];
            $rules[$field] = ['exclude_if:'.$field.'_unlimited,1', 'required_unless:'.$field.'_unlimited,1', 'integer', 'between:0,1000000'];
        }
        $data = $request->validate($rules);
        $normalized = [];
        foreach (['monthly', 'quarterly'] as $interval) {
            $parts = explode('.', str_replace(',', '.', $data[$interval.'_price']));
            $normalized[$interval.'_price_minor'] = (int) $parts[0] * 100 + (int) str_pad($parts[1] ?? '', 2, '0');
        }
        foreach (['max_projects', 'max_explorer_projects', 'max_mentor_projects'] as $field) {
            $normalized[$field] = $request->boolean($field.'_unlimited') ? null : $data[$field];
        }
        $normalized['benefits'] = array_values(array_filter(
            array_map('trim', preg_split('/\r\n|\r|\n/', $data['benefits_text'] ?? '')),
            fn (string $benefit): bool => $benefit !== '',
        ));
        $request->merge($normalized);
    }

    public function savePlan(Request $request, ?Plan $plan = null): JsonResponse|RedirectResponse
    {
        $isForm = $request->boolean('plan_form');
        if ($isForm) {
            $this->preparePlanForm($request);
        }
        $partial = $plan && ! $isForm;
        $required = $partial ? 'sometimes' : 'required';
        $limit = [$partial ? 'sometimes' : 'present', 'nullable', 'integer', 'between:0,1000000'];
        $data = $request->validate([
            'name' => [$required, 'filled', 'string', 'max:120'],
            'is_active' => [$required, 'boolean'],
            'benefits' => [$partial ? 'sometimes' : 'present', 'array', 'list', 'max:50'],
            'benefits.*' => ['required', 'string', 'max:500'],
            'access_level' => [$required, 'integer', 'between:1,1000000'],
            'max_projects' => $limit, 'max_explorer_projects' => $limit, 'max_mentor_projects' => $limit,
            'trial_days' => [$required, 'integer', 'between:0,3650'],
            'monthly_price_minor' => [$required, 'integer', 'between:0,2147483647'],
            'quarterly_price_minor' => [$required, 'integer', 'between:0,2147483647'],
            'version' => [$plan ? 'required' : 'prohibited', 'integer', 'min:1'],
            'request_key' => 'required|uuid',
        ]);

        return DB::transaction(function () use ($plan, $data, $request, $isForm): JsonResponse|RedirectResponse {
            $admin = User::lockForUpdate()->findOrFail($request->user()->id);
            abort_unless($admin->isSuperAdmin() && $admin->hasVerifiedEmail(), 403);
            $key = $data['request_key'];
            $duplicate = SubscriptionEvent::where('actor_id', $admin->id)->where('request_key', $key)->exists();
            if ($duplicate && $isForm) {
                throw ValidationException::withMessages(['request_key' => 'Operațiune deja procesată. Reîncarcă formularul înainte de o nouă salvare.']);
            }
            abort_if($duplicate, 409);
            $record = $plan ? Plan::lockForUpdate()->findOrFail($plan->id) : new Plan;
            $conflict = $plan && $record->version !== (int) $data['version'];
            if ($conflict && $isForm) {
                throw ValidationException::withMessages(['version' => 'Planul a fost modificat între timp. Reîncarcă formularul și verifică valorile înainte de salvare.']);
            }
            abort_if($conflict, 409);
            $totalLimit = array_key_exists('max_projects', $data) ? $data['max_projects'] : $record->max_projects;
            $limitErrors = [];
            if ($totalLimit !== null) {
                foreach (['max_explorer_projects' => 'Explorator', 'max_mentor_projects' => 'Mentor'] as $field => $label) {
                    $typeLimit = array_key_exists($field, $data) ? $data[$field] : $record->{$field};
                    if ($typeLimit === null) {
                        $limitErrors[$field] = 'Limita '.$label.' nu poate fi Nelimitat când limita totală este '.$totalLimit.'.';
                    } elseif ($typeLimit > $totalLimit) {
                        $limitErrors[$field] = 'Limita '.$label.' nu poate depăși limita totală de '.$totalLimit.' proiecte.';
                    }
                }
            }
            if ($limitErrors !== []) {
                throw ValidationException::withMessages($limitErrors);
            }
            $before = $plan ? ($record->terms() + ['is_active' => $record->is_active]) : null;
            unset($data['request_key'], $data['version']);
            $record->fill($data);
            $record->version = $plan ? $record->version + 1 : 1;
            $record->save();
            $record->refresh();
            SubscriptionEvent::record($plan ? 'plan_updated' : 'plan_created', $record->id, $admin, $key,
                details: ['before' => $before, 'after' => $record->terms() + ['is_active' => $record->is_active]]);

            return $isForm
                ? redirect()->route('aura.admin.plans')
                : response()->json($record, $plan ? 200 : 201);
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
