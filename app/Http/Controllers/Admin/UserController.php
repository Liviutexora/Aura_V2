<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ResetUserPasswordRequest;
use App\Models\Plan;
use App\Models\SubscriptionEvent;
use App\Models\User;
use App\Models\UserMembership;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserController extends Controller
{
    /** @var array<string, string> */
    private const SORTABLE_COLUMNS = [
        'name' => 'name',
        'email' => 'email',
        'registered' => 'created_at',
        'status' => 'status',
    ];

    /** @var array<string, string> */
    private const STATUS_LABELS = [
        'active' => 'Activ',
        'inactive' => 'Inactiv',
        'suspended' => 'Suspendat',
    ];

    /** @var array<string, string> */
    private const ROLE_LABELS = [
        'super_admin' => 'SUPER ADMIN',
        'user' => 'Utilizator',
    ];

    public function index(Request $request): View
    {
        $search = $request->string('q')->trim()->toString();
        $status = $request->string('status')->trim()->toString();
        $sort = $request->string('sort')->toString();
        $direction = $request->string('direction')->lower()->toString() === 'asc' ? 'asc' : 'desc';
        $sortColumn = self::SORTABLE_COLUMNS[$sort] ?? 'created_at';

        $users = User::query()
            ->select(['id', 'name', 'email', 'created_at', 'status', 'referral_code', 'sponsor_id'])
            ->with('sponsor:id,name')
            ->withCount('sponsoredUsers')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($status !== '', fn (Builder $query) => $query->where('status', $status))
            ->orderBy($sortColumn, $direction)
            ->orderBy('id', $direction)
            ->paginate(6)
            ->withQueryString();

        $statuses = User::query()
            ->whereNotNull('status')
            ->distinct()
            ->orderBy('status')
            ->pluck('status');

        return view('admin.pages.users', [
            'users' => $users,
            'statuses' => $statuses,
            'statusLabels' => self::STATUS_LABELS,
            'direction' => $direction,
            ...$this->adminLayoutData('Utilizatori'),
        ]);
    }

    public function show(Request $request, User $user): View
    {
        $user->load([
            'profile:id,user_id,phone,locale,timezone',
            'sponsor:id,name',
        ])->loadCount('sponsoredUsers');

        return view('admin.pages.user-show', [
            'user' => $user,
            'membership' => UserMembership::whereBelongsTo($user)->effectiveAt(now())->first(),
            'subscriptionEvents' => $request->query('tab') === 'subscription'
                ? SubscriptionEvent::auditHistory($user)->paginate(10)->withQueryString() : null,
            'statusLabel' => self::STATUS_LABELS[$user->status] ?? ($user->status ?: '—'),
            'roleLabel' => self::ROLE_LABELS[$user->role] ?? ($user->role ?: '—'),
            ...$this->adminLayoutData('Profil utilizator', 'users/'.$user->getKey()),
        ]);
    }

    public function edit(User $user): View
    {
        $membership = UserMembership::whereBelongsTo($user)->effectiveAt(now())->first();

        return view('admin.pages.user-edit', [
            'user' => $user,
            'membership' => $membership,
            'activePlans' => $membership === null ? Plan::activeOptions() : collect(),
            'statusLabel' => self::STATUS_LABELS[$user->status] ?? ($user->status ?: '—'),
            ...$this->adminLayoutData('Editează utilizator', 'users/'.$user->getKey().'/edit'),
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function resetPassword(ResetUserPasswordRequest $request, User $user): RedirectResponse
    {
        $admin = $request->user();
        $validated = $request->validated();

        if (! Hash::check($validated['current_password'], $admin->getAuthPassword())) {
            throw ValidationException::withMessages([
                'current_password' => 'Parola SUPER ADMIN este incorectă.',
            ]);
        }

        $user->forceFill([
            'password' => Hash::make($validated['password']),
        ])->save();

        Log::notice('admin.password_reset', [
            'admin_user_id' => $admin->getKey(),
            'target_user_id' => $user->getKey(),
            'action' => 'password_reset',
            'occurred_at' => now()->toIso8601String(),
        ]);

        return redirect()
            ->route('aura.admin.users.edit', $user)
            ->with('password_reset_status', 'Parola utilizatorului a fost resetată cu succes.');
    }

    /**
     * @return array{
     *     demoData: array{nav: array<int, mixed>},
     *     demoPage: string,
     *     pageTitle: string,
     *     auraAdminServerRendered: true
     * }
     */
    private function adminLayoutData(string $pageTitle, string $demoPage = 'users'): array
    {
        $adminDemoData = json_decode(
            file_get_contents(resource_path('data/admin-demo.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        return [
            'demoData' => ['nav' => $adminDemoData['nav']],
            'demoPage' => $demoPage,
            'pageTitle' => $pageTitle,
            'auraAdminServerRendered' => true,
        ];
    }
}
