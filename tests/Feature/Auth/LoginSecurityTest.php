<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Validated;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class LoginSecurityTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const PASSWORD = 'login-password-123';

    private const LOGIN_ERROR = 'Datele de autentificare nu sunt valide.';

    #[TestWith(['user', 'aura.login', 'aura.dashboard'])]
    #[TestWith(['super_admin', 'aura.admin.login', 'aura.admin.index'])]
    public function test_active_account_enters_its_area_with_a_new_session(string $role, string $loginForm, string $destination): void
    {
        $user = $this->createUser($role);
        $cookieName = config('session.cookie');
        $guestSessionId = $this->get(route($loginForm))->assertOk()->getCookie($cookieName)->getValue();

        $response = $this->withCookie($cookieName, $guestSessionId)->post(route($loginForm.'.store'), [
            'email' => strtoupper($user->email),
            'password' => self::PASSWORD,
            'remember' => '1',
        ]);

        $response->assertRedirectToRoute($destination);
        $response->assertCookie(Auth::guard('web')->getRecallerName());
        $this->assertNotSame($guestSessionId, $response->getCookie($cookieName)->getValue());
        $this->assertAuthenticatedAs($user);
        $this->assertSame($user->getAuthPassword(), $user->fresh()->getAuthPassword());
    }

    #[TestWith(['user', 'active', 'aura.login', 'incorrect-password'], 'wrong password')]
    #[TestWith(['user', 'suspended', 'aura.login', self::PASSWORD], 'suspended user')]
    #[TestWith(['super_admin', 'disabled', 'aura.admin.login', self::PASSWORD], 'disabled admin')]
    #[TestWith(['super_admin', 'active', 'aura.login', self::PASSWORD], 'admin on user form')]
    #[TestWith(['user', 'active', 'aura.admin.login', self::PASSWORD], 'user on admin form')]
    public function test_rejected_account_gets_generic_error_and_keeps_its_old_hash(
        string $role,
        string $status,
        string $loginForm,
        string $password,
    ): void {
        $user = $this->createUser($role, $status);
        $oldHash = $user->getAuthPassword();
        config(['hashing.bcrypt.rounds' => 5]);
        Hash::forgetDrivers();
        $this->assertTrue(Hash::needsRehash($oldHash));
        Event::fake([Validated::class]);

        $response = $this->from(route($loginForm))->post(route($loginForm.'.store'), [
            'email' => $user->email,
            'password' => $password,
            'status' => 'active',
            'role' => $loginForm === 'aura.admin.login' ? 'super_admin' : 'user',
        ]);

        $response->assertRedirectToRoute($loginForm);
        $response->assertSessionHasErrors(['email' => self::LOGIN_ERROR]);
        $this->assertGuest();
        $this->assertSame($oldHash, $user->fresh()->getAuthPassword());
        Event::assertDispatchedTimes(Validated::class, $password === self::PASSWORD ? 1 : 0);
    }

    public function test_unknown_email_gets_the_same_generic_error(): void
    {
        $response = $this->from(route('aura.login'))->post(route('aura.login.store'), [
            'email' => 'absent@example.test',
            'password' => self::PASSWORD,
        ]);

        $response->assertRedirectToRoute('aura.login');
        $response->assertSessionHasErrors(['email' => self::LOGIN_ERROR]);
        $this->assertGuest();
    }

    #[TestWith(['bcrypt'], 'higher bcrypt cost')]
    #[TestWith(['argon2id'], 'migration from bcrypt to Argon2id')]
    public function test_successful_login_upgrades_the_hash_and_preserves_the_password(string $newDriver): void
    {
        if ($newDriver === 'argon2id' && ! in_array('argon2id', password_algos(), true)) {
            $this->markTestSkipped('PHP lacks Argon2id support.');
        }

        $user = $this->createUser();
        $oldHash = $user->getAuthPassword();
        config([
            'hashing.driver' => $newDriver,
            'hashing.bcrypt.rounds' => 5,
            'hashing.argon.verify' => false,
            'hashing.argon.memory' => 1024,
            'hashing.argon.time' => 2,
        ]);
        Hash::forgetDrivers();
        $this->assertTrue(Hash::needsRehash($oldHash));

        $response = $this->post(route('aura.login.store'), ['email' => $user->email, 'password' => self::PASSWORD]);

        $response->assertRedirectToRoute('aura.dashboard');
        $this->assertAuthenticatedAs($user);
        $newHash = $user->fresh()->getAuthPassword();
        $this->assertNotSame($oldHash, $newHash);
        $this->assertFalse(Hash::needsRehash($newHash));
        $this->assertTrue(Hash::check(self::PASSWORD, $newHash));

        $cookieName = config('session.cookie');
        Auth::forgetGuards();
        $this->withCookie($cookieName, $response->getCookie($cookieName)->getValue())
            ->get(route('aura.dashboard'))
            ->assertOk()
            ->assertSessionHas('password_hash_web', Auth::guard('web')->hashPasswordForCookie($newHash));
    }

    #[TestWith(['user', 'aura.login.store'])]
    #[TestWith(['super_admin', 'aura.admin.login.store'])]
    public function test_five_failed_attempts_block_even_correct_credentials(string $role, string $loginRoute): void
    {
        $user = $this->createUser($role);
        $this->freezeTime();
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post(route($loginRoute), ['email' => $user->email, 'password' => 'incorrect-password'])
                ->assertSessionHasErrors(['email' => self::LOGIN_ERROR]);
        }

        $response = $this->post(route($loginRoute), ['email' => $user->email, 'password' => self::PASSWORD]);

        $response->assertTooManyRequests();
        $this->assertGuest();
    }

    private function createUser(string $role = 'user', string $status = 'active'): User
    {
        return User::factory()->create([
            'email' => 'Mixed.Case@example.test',
            'password' => Hash::make(self::PASSWORD, ['rounds' => 4]),
            'role' => $role,
            'status' => $status,
            'referral_code' => 'LOGINACCOUNT',
        ]);
    }
}
