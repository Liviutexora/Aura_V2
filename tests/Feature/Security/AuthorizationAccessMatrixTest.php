<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthorizationAccessMatrixTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const CURRENT_PASSWORD = 'current-password-123';

    private const NEW_PASSWORD = 'new-password-456';

    #[DataProvider('accessActors')]
    public function test_settings_page_enforces_access_matrix(
        ?string $role,
        ?string $status,
        string $userOutcome,
        string $adminOutcome,
    ): void {
        $actor = $this->createActor($role, $status);
        $this->authenticate($actor);

        $response = $this->get(route('aura.settings'));

        $this->assertPageOutcome($response, $userOutcome, false);
    }

    #[DataProvider('accessActors')]
    public function test_profile_update_enforces_access_matrix(
        ?string $role,
        ?string $status,
        string $userOutcome,
        string $adminOutcome,
    ): void {
        $actor = $this->createActor($role, $status);
        $subject = $actor ?? $this->createUser(
            email: 'guest-profile-target@example.test',
            referralCode: 'GUESTPROFILE',
        );
        $originalName = $subject->name;
        $this->authenticate($actor);

        $response = $this->from(route('aura.settings'))
            ->post(route('aura.settings.profile.update'), [
                'name' => 'Matrix Updated Name',
                'email' => $subject->email,
                'id' => $subject->getKey(),
                'user_id' => $subject->getKey(),
            ]);

        $this->assertWriteOutcome($response, $userOutcome, false, 'aura.settings');
        $this->assertSame(
            $userOutcome === 'allowed' ? 'Matrix Updated Name' : $originalName,
            $subject->fresh()->name,
        );
    }

    #[DataProvider('accessActors')]
    public function test_password_change_enforces_access_matrix(
        ?string $role,
        ?string $status,
        string $userOutcome,
        string $adminOutcome,
    ): void {
        $actor = $this->createActor($role, $status);
        $subject = $actor ?? $this->createUser(
            email: 'guest-password-target@example.test',
            referralCode: 'GUESTPASSWORD',
        );
        $this->authenticate($actor);

        $response = $this->from(route('aura.settings'))
            ->post(route('aura.settings.password.update'), [
                'id' => $subject->getKey(),
                'user_id' => $subject->getKey(),
                'current_password' => self::CURRENT_PASSWORD,
                'password' => self::NEW_PASSWORD,
                'password_confirmation' => self::NEW_PASSWORD,
            ]);

        $this->assertWriteOutcome($response, $userOutcome, false, 'aura.settings');

        $persistedPassword = $subject->fresh()->getAuthPassword();

        $this->assertSame($userOutcome === 'allowed', Hash::check(self::NEW_PASSWORD, $persistedPassword));
        $this->assertSame($userOutcome !== 'allowed', Hash::check(self::CURRENT_PASSWORD, $persistedPassword));
    }

    #[DataProvider('accessActors')]
    public function test_admin_users_index_enforces_access_matrix(
        ?string $role,
        ?string $status,
        string $userOutcome,
        string $adminOutcome,
    ): void {
        $actor = $this->createActor($role, $status);
        $this->authenticate($actor);

        $response = $this->get(route('aura.admin.users'));

        $this->assertPageOutcome($response, $adminOutcome, true);
    }

    #[DataProvider('accessActors')]
    public function test_admin_user_show_enforces_access_matrix(
        ?string $role,
        ?string $status,
        string $userOutcome,
        string $adminOutcome,
    ): void {
        $actor = $this->createActor($role, $status);
        $target = $this->createUser(
            email: 'show-target@example.test',
            referralCode: 'SHOWTARGET',
        );
        $this->authenticate($actor);

        $response = $this->get(route('aura.admin.users.show', $target));

        $this->assertPageOutcome($response, $adminOutcome, true);
    }

    #[DataProvider('accessActors')]
    public function test_admin_user_edit_enforces_access_matrix(
        ?string $role,
        ?string $status,
        string $userOutcome,
        string $adminOutcome,
    ): void {
        $actor = $this->createActor($role, $status);
        $target = $this->createUser(
            email: 'edit-target@example.test',
            referralCode: 'EDITTARGET',
        );
        $this->authenticate($actor);

        $response = $this->get(route('aura.admin.users.edit', $target));

        $this->assertPageOutcome($response, $adminOutcome, true);
    }

    #[DataProvider('accessActors')]
    public function test_admin_password_reset_enforces_access_matrix(
        ?string $role,
        ?string $status,
        string $userOutcome,
        string $adminOutcome,
    ): void {
        $actor = $this->createActor($role, $status);
        $target = $this->createUser(
            email: 'reset-target@example.test',
            referralCode: 'RESETTARGET',
        );
        $this->authenticate($actor);

        $response = $this->post(route('aura.admin.users.password.reset', $target), [
            'current_password' => self::CURRENT_PASSWORD,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ]);

        $this->assertWriteOutcome(
            $response,
            $adminOutcome,
            true,
            'aura.admin.users.edit',
            [$target],
        );

        $persistedPassword = $target->fresh()->getAuthPassword();

        $this->assertSame($adminOutcome === 'allowed', Hash::check(self::NEW_PASSWORD, $persistedPassword));
        $this->assertSame($adminOutcome !== 'allowed', Hash::check(self::CURRENT_PASSWORD, $persistedPassword));
    }

    #[DataProvider('accessActors')]
    public function test_legacy_hash_admin_entry_enforces_access_matrix(
        ?string $role,
        ?string $status,
        string $userOutcome,
        string $adminOutcome,
    ): void {
        $actor = $this->createActor($role, $status);
        $this->authenticate($actor);

        // URL fragments are not sent to the server, so /admin/#/users resolves as /admin/.
        $response = $this->get(route('aura.admin.index'));

        $this->assertPageOutcome($response, $adminOutcome, true);
    }

    /**
     * @return array<string, array{0: ?string, 1: ?string, 2: string, 3: string}>
     */
    public static function accessActors(): array
    {
        return [
            'unauthenticated visitor' => [null, null, 'guest', 'guest'],
            'active user' => ['user', 'active', 'allowed', 'forbidden'],
            'inactive user' => ['user', 'disabled', 'forbidden', 'forbidden'],
            'active super admin' => ['super_admin', 'active', 'allowed', 'allowed'],
            'inactive super admin' => ['super_admin', 'disabled', 'forbidden', 'forbidden'],
        ];
    }

    private function createActor(?string $role, ?string $status): ?User
    {
        if ($role === null || $status === null) {
            return null;
        }

        return $this->createUser(
            email: $role.'-'.$status.'@example.test',
            referralCode: strtoupper(str_replace('_', '', $role)).strtoupper($status),
            role: $role,
            status: $status,
        );
    }

    private function authenticate(?User $actor): void
    {
        if ($actor !== null) {
            $this->actingAs($actor);
        }
    }

    private function assertPageOutcome(TestResponse $response, string $outcome, bool $adminRoute): void
    {
        match ($outcome) {
            'allowed' => $response->assertOk(),
            'guest' => $response->assertRedirectToRoute($adminRoute ? 'aura.admin.login' : 'aura.login'),
            'forbidden' => $response->assertForbidden(),
        };
    }

    /**
     * @param  array<int, mixed>  $routeParameters
     */
    private function assertWriteOutcome(
        TestResponse $response,
        string $outcome,
        bool $adminRoute,
        string $successRoute,
        array $routeParameters = [],
    ): void {
        match ($outcome) {
            'allowed' => $response->assertRedirectToRoute($successRoute, $routeParameters),
            'guest' => $response->assertRedirectToRoute($adminRoute ? 'aura.admin.login' : 'aura.login'),
            'forbidden' => $response->assertForbidden(),
        };
    }

    private function createUser(
        string $email,
        string $referralCode,
        string $role = 'user',
        string $status = 'active',
    ): User {
        return User::factory()->create([
            'name' => 'Matrix User',
            'email' => $email,
            'password' => Hash::make(self::CURRENT_PASSWORD),
            'role' => $role,
            'status' => $status,
            'referral_code' => $referralCode,
        ]);
    }
}
