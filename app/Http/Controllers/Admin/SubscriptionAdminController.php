<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\SubscriptionEvent;
use App\Models\SubscriptionPromoCode;
use App\Models\User;
use App\Models\UserMembership;
use App\Services\MembershipService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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
        return response()->json(UserMembership::withCancelled()->latest('id')->paginate(50));
    }

    private function subscriptionLayout(string $page, string $title): array
    {
        return [
            'demoData' => ['nav' => json_decode(file_get_contents(resource_path('data/admin-navigation.json')), true, 512, JSON_THROW_ON_ERROR)],
            'demoPage' => $page,
            'pageTitle' => $title,
            'auraAdminServerRendered' => true,
        ];
    }

    public function subscriptions(Request $request): View
    {
        $layout = $this->subscriptionLayout('subscriptions', 'Abonamente utilizatori');
        if ($request->query('tab') === 'history') {
            return view('admin.pages.subscriptions', $layout + [
                'events' => SubscriptionEvent::auditHistory()->paginate(10)->withQueryString(),
            ]);
        }

        $filters = $request->validate([
            'q' => 'nullable|string|max:255', 'status' => 'nullable|in:active,scheduled,expired,cancelled',
            'range' => 'nullable|in:7,30,90', 'sort' => 'nullable|in:user,plan,source,start,end,status',
            'direction' => 'nullable|in:asc,desc',
        ]);
        $at = CarbonImmutable::now()->startOfSecond();
        $statusSql = "case when closed_at < starts_at then 'cancelled' when ends_at <= ? then 'expired' when starts_at > ? then 'scheduled' else 'active' end";
        $columns = ['user' => 'users.name', 'plan' => 'terms->name', 'source' => 'source',
            'start' => 'starts_at', 'end' => 'ends_at', 'status' => 'period_status'];
        $direction = $filters['direction'] ?? 'desc';
        $periods = UserMembership::withCancelled()->join('users', 'users.id', '=', 'user_memberships.user_id')
            ->select(['user_memberships.*', 'users.name as user_name', 'users.email as user_email'])
            ->selectRaw($statusSql.' as period_status', [$at, $at]);
        if (isset($filters['q'])) {
            $term = '%'.$filters['q'].'%';
            $periods->where(fn (Builder $query) => $query->where('users.name', 'like', $term)
                ->orWhere('users.email', 'like', $term)->orWhere('terms->name', 'like', $term));
        }
        if (isset($filters['status'])) {
            $periods->whereRaw($statusSql.' = ?', [$at, $at, $filters['status']]);
        }
        if (isset($filters['range'])) {
            $periods->where('starts_at', '<=', $at)->where('ends_at', '>', $at->subDays((int) $filters['range']));
        }
        $periods->orderBy($columns[$filters['sort'] ?? 'start'], $direction)->orderBy('user_memberships.id', $direction);

        if ($request->has('manage_user')) {
            $recipientId = $request->validate(['manage_user' => 'required|integer|min:1'])['manage_user'];
            $recipient = User::select('id', 'name', 'email')->findOrFail($recipientId);
            $layout['recipient'] = $recipient;
            $layout['currentPeriod'] = UserMembership::whereBelongsTo($recipient)->effectiveAt($at)->first();
            $layout['activePlans'] = Plan::activeOptions();
        }

        return view('admin.pages.subscriptions', $layout + [
            'periods' => $periods->paginate(6)->withQueryString(), 'filters' => $filters, 'direction' => $direction, 'at' => $at,
        ]);
    }

    public function cancelScheduled(Request $request, int $period, MembershipService $service): Response|RedirectResponse
    {
        $data = $request->validate([
            'starts_at' => 'required|integer|min:1',
            'ends_at' => 'required|integer|gt:starts_at',
            'request_key' => 'required|uuid',
        ]);
        $service->cancelScheduled($request->user(), $period, $data['starts_at'], $data['ends_at'], $data['request_key']);

        return $request->expectsJson()
            ? response()->noContent()
            : redirect()->back(fallback: route('aura.admin.subscriptions'))->with('period_status', 'Perioada programată a fost anulată.');
    }

    public function events(): JsonResponse
    {
        return response()->json(SubscriptionEvent::auditHistory()->paginate(50));
    }

    public function codes(Request $request): Response|JsonResponse
    {
        $filters = $request->validate(['q' => 'nullable|string|max:255', 'status' => 'nullable|in:disponibil,utilizat,expirat,revocat',
            'tab' => 'nullable|in:disponibile,arhiva']);
        $isPage = $request->routeIs('aura.admin.promo-codes');
        $status = $filters['status'] ?? null;
        $archiveStatuses = ['utilizat', 'revocat', 'expirat'];
        $tab = $filters['tab'] ?? (in_array($status, $archiveStatuses, true) ? 'arhiva' : 'disponibile');
        if ($isPage) {
            $status = $tab === 'disponibile' ? 'disponibil' : (in_array($status, $archiveStatuses, true) ? $status : 'arhiva');
        }
        $inventory = SubscriptionPromoCode::inventory($status)
            ->with(['plan:id,name', 'reservedUser:id,name,email', 'membership:id,user_id', 'membership.user:id,name,email']);
        if (isset($filters['q'])) {
            $term = '%'.$filters['q'].'%';
            $person = fn (Builder $users) => $users->where('name', 'like', $term)->orWhere('email', 'like', $term);
            $inventory->where(fn (Builder $query) => $query->whereRelation('plan', 'name', 'like', $term)
                ->orWhereHas('reservedUser', $person)->orWhereHas('membership.user', $person));
        }
        $codes = $inventory->latest('id')->paginate($isPage ? 6 : 50)->withQueryString();
        if ($isPage) {
            return response()->view('admin.pages.promo-codes', $this->subscriptionLayout('promo-codes', 'Coduri promoționale') + [
                'codes' => $codes, 'filters' => $filters, 'activePlans' => Plan::activeOptions(),
                'tab' => $tab, 'statuses' => $tab === 'arhiva' ? $archiveStatuses : ['disponibil'],
            ], headers: ['Cache-Control' => 'no-store']);
        }

        $codes->getCollection()->each->append('status');

        return response()->json($codes, headers: ['Cache-Control' => 'no-store']);
    }

    public function grant(Request $request, User $user, MembershipService $service): JsonResponse|RedirectResponse
    {
        $data = $request->validate(['plan_id' => 'required|integer|min:1', 'duration_days' => 'required|integer|between:1,3650',
            'timing' => 'required|in:now,period_end', 'reason' => 'required|string|max:1000', 'request_key' => 'required|uuid',
            'confirm_replace_membership_id' => 'nullable|integer|min:1|prohibited_unless:timing,now']);

        $membership = $service->manual($request->user(), $user, $data['plan_id'], $data['duration_days'],
            $data['timing'], $data['reason'], $data['request_key'], $data['confirm_replace_membership_id'] ?? null);

        return $request->boolean('membership_form')
            ? redirect()->route('aura.admin.users.show', ['user' => $user, 'tab' => 'subscription'])
                ->with('membership_status', 'Abonamentul a fost salvat.')
            : response()->json($membership, 201);
    }

    public function issueCode(Request $request, MembershipService $service): JsonResponse
    {
        $data = $request->validate(['plan_id' => 'required|integer|min:1', 'duration_days' => 'required|integer|between:1,3650',
            'quantity' => 'sometimes|integer|between:1,100', 'reserved_user_id' => 'nullable|integer|exists:users,id',
            'expires_at' => 'required|date|after:now', 'reason' => 'nullable|string|max:1000', 'request_key' => 'required|uuid']);
        $codes = $service->issueCodes($request->user(), $data['plan_id'], $data['duration_days'], $data['quantity'] ?? 1,
            $data['reserved_user_id'] ?? null, CarbonImmutable::parse($data['expires_at']), $data['request_key'], $data['reason'] ?? null);

        return response()->json([
            'id' => $codes[0]['id'],
            'code' => $codes[0]['code'],
            'codes' => $codes,
        ], 201, ['Cache-Control' => 'no-store']);
    }

    public function revokeCode(Request $request, SubscriptionPromoCode $promoCode, MembershipService $service): JsonResponse|RedirectResponse
    {
        $data = $request->validate(['request_key' => 'required|uuid']);
        $promo = $service->revokeCode($request->user(), $promoCode, $data['request_key']);

        return $request->expectsJson()
            ? response()->json($promo->append('status'))
            : redirect()->back(fallback: route('aura.admin.promo-codes'))->with('promo_status', 'Codul promoțional a fost revocat.');
    }

    public function deleteCode(Request $request, int $promoCode, MembershipService $service): Response|RedirectResponse
    {
        $data = $request->validate([
            'request_key' => 'required|uuid',
            'confirm_delete' => 'accepted',
        ], ['confirm_delete.accepted' => 'Confirmă ștergerea definitivă a cuponului.']);
        $service->deleteCode($request->user(), $promoCode, $data['request_key']);

        return $request->expectsJson()
            ? response()->noContent()
            : redirect()->back(fallback: route('aura.admin.promo-codes'))->with('promo_status', 'Cuponul a fost șters definitiv.');
    }
}
