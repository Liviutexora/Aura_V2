<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\SessionGuard;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordSessionInvalidationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const OLD_PASSWORD = 'old-password-123';

    private const NEW_PASSWORD = 'new-password-456';

    private const ADMIN_PASSWORD = 'admin-password-123';

    public function test_user_password_change_keeps_current_session_and_rejects_old_password(): void
    {
        $user = $this->createUser(
            email: 'user-password-change@example.test',
            referralCode: 'USERPASSCHANGE',
            password: self::OLD_PASSWORD,
        );
        $sessionKey = $this->passwordSessionKey();

        $response = $this->actingAs($user)
            ->withSession([$sessionKey => $this->sessionPasswordHash($user)])
            ->from(route('aura.settings'))
            ->post(route('aura.settings.password.update'), [
                'current_password' => self::OLD_PASSWORD,
                'password' => self::NEW_PASSWORD,
                'password_confirmation' => self::NEW_PASSWORD,
            ]);

        $user = $user->fresh();

        $response->assertRedirect(route('aura.settings'));
        $response->assertSessionHas('password_status', 'Parola a fost schimbată.');
        $response->assertSessionHas($sessionKey, $this->sessionPasswordHash($user));
        $this->assertAuthenticatedAs($user);
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $user->getAuthPassword()));
        $this->assertFalse(Hash::check(self::OLD_PASSWORD, $user->getAuthPassword()));

        $this->actingAsGuest();
        $this->app['session']->flush();

        $this->post(route('aura.login.store'), [
            'email' => $user->email,
            'password' => self::OLD_PASSWORD,
        ])->assertSessionHasErrors([
            'email' => 'Datele de autentificare nu sunt valide.',
        ]);
        $this->assertGuest();
    }

    public function test_secondary_user_session_is_rejected_after_password_change(): void
    {
        $user = $this->createUser(
            email: 'user-secondary-session@example.test',
            referralCode: 'USERSECONDARY',
            password: self::OLD_PASSWORD,
        );
        $staleSessionHash = $this->sessionPasswordHash($user);

        $user->forceFill(['password' => Hash::make(self::NEW_PASSWORD)])->save();

        $response = $this->actingAs($user->fresh())
            ->withSession([$this->passwordSessionKey() => $staleSessionHash])
            ->get(route('aura.dashboard'));

        $response->assertRedirect(route('aura.login'));
        $this->assertGuest();
    }

    public function test_super_admin_reset_keeps_admin_session_and_rejects_target_old_password(): void
    {
        $admin = $this->createUser(
            email: 'admin-password-reset@example.test',
            referralCode: 'ADMINPASSRESET',
            password: self::ADMIN_PASSWORD,
            role: 'super_admin',
        );
        $target = $this->createUser(
            email: 'target-password-reset@example.test',
            referralCode: 'TARGETPASSRESET',
            password: self::OLD_PASSWORD,
        );
        $sessionKey = $this->passwordSessionKey();

        $response = $this->actingAs($admin)
            ->withSession([$sessionKey => $this->sessionPasswordHash($admin)])
            ->post(route('aura.admin.users.password.reset', $target), [
                'current_password' => self::ADMIN_PASSWORD,
                'password' => self::NEW_PASSWORD,
                'password_confirmation' => self::NEW_PASSWORD,
            ]);

        $admin = $admin->fresh();
        $target = $target->fresh();

        $response->assertRedirect(route('aura.admin.users.edit', $target));
        $response->assertSessionHas('password_reset_status', 'Parola utilizatorului a fost resetată cu succes.');
        $response->assertSessionHas($sessionKey, $this->sessionPasswordHash($admin));
        $this->assertAuthenticatedAs($admin);
        $this->assertTrue(Hash::check(self::ADMIN_PASSWORD, $admin->getAuthPassword()));
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $target->getAuthPassword()));
        $this->assertFalse(Hash::check(self::OLD_PASSWORD, $target->getAuthPassword()));

        $this->actingAsGuest();
        $this->app['session']->flush();

        $this->post(route('aura.login.store'), [
            'email' => $target->email,
            'password' => self::OLD_PASSWORD,
        ])->assertSessionHasErrors([
            'email' => 'Datele de autentificare nu sunt valide.',
        ]);
        $this->assertGuest();
    }

    public function test_target_session_is_rejected_after_super_admin_reset(): void
    {
        $admin = $this->createUser(
            email: 'admin-session-preservation@example.test',
            referralCode: 'ADMINSESSION',
            password: self::ADMIN_PASSWORD,
            role: 'super_admin',
        );
        $target = $this->createUser(
            email: 'target-stale-session@example.test',
            referralCode: 'TARGETSESSION',
            password: self::OLD_PASSWORD,
        );
        $staleTargetSessionHash = $this->sessionPasswordHash($target);

        $this->actingAs($admin)
            ->withSession([$this->passwordSessionKey() => $this->sessionPasswordHash($admin)])
            ->post(route('aura.admin.users.password.reset', $target), [
                'current_password' => self::ADMIN_PASSWORD,
                'password' => self::NEW_PASSWORD,
                'password_confirmation' => self::NEW_PASSWORD,
            ])
            ->assertRedirect(route('aura.admin.users.edit', $target));

        $response = $this->actingAs($target->fresh())
            ->withSession([$this->passwordSessionKey() => $staleTargetSessionHash])
            ->get(route('aura.dashboard'));

        $response->assertRedirect(route('aura.login'));
        $this->assertGuest();
    }

    private function createUser(
        string $email,
        string $referralCode,
        string $password,
        string $role = 'user',
    ): User {
        return User::factory()->create([
            'name' => $role === 'super_admin' ? 'Test SUPER ADMIN' : 'Test User',
            'email' => $email,
            'password' => Hash::make($password),
            'role' => $role,
            'status' => 'active',
            'referral_code' => $referralCode,
        ]);
    }

    private function passwordSessionKey(): string
    {
        return 'password_hash_'.Auth::getDefaultDriver();
    }

    private function sessionPasswordHash(User $user): string
    {
        /** @var SessionGuard $guard */
        $guard = Auth::guard();

        return $guard->hashPasswordForCookie($user->getAuthPassword());
    }
}
