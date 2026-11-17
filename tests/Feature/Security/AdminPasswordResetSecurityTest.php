<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminPasswordResetSecurityTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const ADMIN_PASSWORD = 'admin-password-123';

    private const TARGET_PASSWORD = 'target-password-123';

    private const NEW_PASSWORD = 'new-password-456';

    public function test_incorrect_super_admin_password_rejects_reset(): void
    {
        [$admin, $target] = $this->createAdminAndTarget('WRONGADMINPASS', 'WRONGTARGETPASS');
        $originalTargetHash = $target->getAuthPassword();

        $response = $this->actingAs($admin)
            ->from(route('aura.admin.users.edit', $target))
            ->post(route('aura.admin.users.password.reset', $target), [
                'current_password' => 'incorrect-admin-password',
                'password' => self::NEW_PASSWORD,
                'password_confirmation' => self::NEW_PASSWORD,
            ]);

        $response->assertRedirectToRoute('aura.admin.users.edit', [$target]);
        $response->assertSessionHasErrors([
            'current_password' => 'Parola SUPER ADMIN este incorectă.',
        ]);
        $response->assertSessionMissing('password_reset_status');
        $this->assertSame($originalTargetHash, $target->fresh()->getAuthPassword());
    }

    public function test_mismatched_new_password_confirmation_rejects_reset(): void
    {
        [$admin, $target] = $this->createAdminAndTarget('MISMATCHADMIN', 'MISMATCHTARGET');
        $originalTargetHash = $target->getAuthPassword();

        $response = $this->actingAs($admin)
            ->from(route('aura.admin.users.edit', $target))
            ->post(route('aura.admin.users.password.reset', $target), [
                'current_password' => self::ADMIN_PASSWORD,
                'password' => self::NEW_PASSWORD,
                'password_confirmation' => 'different-password-789',
            ]);

        $response->assertRedirectToRoute('aura.admin.users.edit', [$target]);
        $response->assertSessionHasErrors([
            'password' => 'Parolele noi nu coincid.',
        ]);
        $response->assertSessionMissing('password_reset_status');
        $this->assertSame($originalTargetHash, $target->fresh()->getAuthPassword());
    }

    public function test_short_new_password_rejects_reset(): void
    {
        [$admin, $target] = $this->createAdminAndTarget('SHORTADMIN', 'SHORTTARGET');
        $originalTargetHash = $target->getAuthPassword();

        $response = $this->actingAs($admin)
            ->from(route('aura.admin.users.edit', $target))
            ->post(route('aura.admin.users.password.reset', $target), [
                'current_password' => self::ADMIN_PASSWORD,
                'password' => 'short7',
                'password_confirmation' => 'short7',
            ]);

        $response->assertRedirectToRoute('aura.admin.users.edit', [$target]);
        $response->assertSessionHasErrors([
            'password' => 'Parola nouă trebuie să aibă cel puțin 8 caractere.',
        ]);
        $response->assertSessionMissing('password_reset_status');
        $this->assertSame($originalTargetHash, $target->fresh()->getAuthPassword());
    }

    public function test_super_admin_cannot_reset_own_password_through_admin_endpoint(): void
    {
        $admin = $this->createUser(
            name: 'Self Reset Admin',
            email: 'self-reset-admin@example.test',
            referralCode: 'SELFRESETADMIN',
            password: self::ADMIN_PASSWORD,
            role: 'super_admin',
        );
        $originalAdminHash = $admin->getAuthPassword();

        $response = $this->actingAs($admin)
            ->post(route('aura.admin.users.password.reset', $admin), $this->validPayload());

        $response->assertForbidden();
        $this->assertSame($originalAdminHash, $admin->fresh()->getAuthPassword());
    }

    public function test_super_admin_cannot_reset_another_super_admin_password(): void
    {
        $admin = $this->createUser(
            name: 'Acting Admin',
            email: 'acting-admin@example.test',
            referralCode: 'ACTINGADMIN',
            password: self::ADMIN_PASSWORD,
            role: 'super_admin',
        );
        $otherAdmin = $this->createUser(
            name: 'Other Admin',
            email: 'other-admin@example.test',
            referralCode: 'OTHERADMIN',
            password: self::TARGET_PASSWORD,
            role: 'super_admin',
        );
        $originalTargetHash = $otherAdmin->getAuthPassword();

        $response = $this->actingAs($admin)
            ->post(route('aura.admin.users.password.reset', $otherAdmin), $this->validPayload());

        $response->assertForbidden();
        $this->assertSame($originalTargetHash, $otherAdmin->fresh()->getAuthPassword());
    }

    public function test_regular_user_cannot_execute_administrative_password_reset(): void
    {
        $user = $this->createUser(
            name: 'Regular User',
            email: 'regular-reset-user@example.test',
            referralCode: 'REGULARRESET',
            password: self::ADMIN_PASSWORD,
        );
        $target = $this->createUser(
            name: 'Reset Target',
            email: 'regular-reset-target@example.test',
            referralCode: 'REGULARTARGET',
            password: self::TARGET_PASSWORD,
        );
        $originalTargetHash = $target->getAuthPassword();

        $response = $this->actingAs($user)
            ->post(route('aura.admin.users.password.reset', $target), $this->validPayload());

        $response->assertForbidden();
        $this->assertSame($originalTargetHash, $target->fresh()->getAuthPassword());
    }

    public function test_administrative_password_reset_rejects_request_without_csrf_token(): void
    {
        [$admin, $target] = $this->createAdminAndTarget('CSRFADMIN', 'CSRFTARGET');
        $originalTargetHash = $target->getAuthPassword();
        $originalEnvironment = $this->app['env'];
        $this->app['env'] = 'production';

        try {
            $response = $this->actingAs($admin)
                ->post(route('aura.admin.users.password.reset', $target), $this->validPayload());
        } finally {
            $this->app['env'] = $originalEnvironment;
        }

        $response->assertStatus(419);
        $this->assertSame($originalTargetHash, $target->fresh()->getAuthPassword());
    }

    public function test_administrative_password_reset_limits_repeated_confirmation_attempts(): void
    {
        [$admin, $target] = $this->createAdminAndTarget('THROTTLEADMIN', 'THROTTLETARGET');
        $originalTargetHash = $target->getAuthPassword();
        $invalidPayload = [
            'current_password' => 'incorrect-admin-password',
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ];
        $this->actingAs($admin);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post(route('aura.admin.users.password.reset', $target), $invalidPayload)
                ->assertSessionHasErrors([
                    'current_password' => 'Parola SUPER ADMIN este incorectă.',
                ]);
        }

        $response = $this->post(route('aura.admin.users.password.reset', $target), $invalidPayload);

        $response->assertTooManyRequests();
        $this->assertSame($originalTargetHash, $target->fresh()->getAuthPassword());
    }

    /** @return array{0: User, 1: User} */
    private function createAdminAndTarget(string $adminReferralCode, string $targetReferralCode): array
    {
        return [
            $this->createUser(
                name: 'Test SUPER ADMIN',
                email: strtolower($adminReferralCode).'@example.test',
                referralCode: $adminReferralCode,
                password: self::ADMIN_PASSWORD,
                role: 'super_admin',
            ),
            $this->createUser(
                name: 'Target User',
                email: strtolower($targetReferralCode).'@example.test',
                referralCode: $targetReferralCode,
                password: self::TARGET_PASSWORD,
            ),
        ];
    }

    /** @return array{current_password: string, password: string, password_confirmation: string} */
    private function validPayload(): array
    {
        return [
            'current_password' => self::ADMIN_PASSWORD,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ];
    }

    private function createUser(
        string $name,
        string $email,
        string $referralCode,
        string $password,
        string $role = 'user',
    ): User {
        return User::factory()->create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => $role,
            'status' => 'active',
            'referral_code' => $referralCode,
        ]);
    }
}
