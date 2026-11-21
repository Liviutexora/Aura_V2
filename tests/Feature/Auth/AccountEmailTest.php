<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\ResetAccountPassword;
use App\Notifications\VerifyEmailCode;
use App\Services\AccountMail;
use App\Services\LoginAttempts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\TestWith;
use RuntimeException;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Tests\TestCase;

class AccountEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_the_session_owner_can_verify_and_a_code_is_consumed_once(): void
    {
        Notification::fake();
        $owner = User::factory()->unverified()->create()->refresh();
        $other = User::factory()->unverified()->create()->refresh();
        $this->actingAs($owner)->get('/settings')->assertRedirectToRoute('aura.verify-email');
        $this->get('/')->assertRedirectToRoute('aura.verify-email');
        $this->post('/settings/profile', ['email' => $other->email])->assertRedirectToRoute('aura.verify-email');
        $this->post('/verify-email/resend')->assertSessionHas('status');
        $code = Notification::sent($owner, VerifyEmailCode::class)->sole()->code;
        $this->assertMatchesRegularExpression('/^[0-9]{6}$/D', $code);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $owner->fresh()->email_code_digest);
        $this->get('/verify-email')->assertOk()->assertSee($owner->email)->assertSee('Retrimite codul');
        $this->actingAs($other)->post('/verify-email', ['code' => $code, 'user_id' => $owner->id])->assertSessionHasErrors('code');
        $this->assertFalse($owner->fresh()->hasVerifiedEmail());
        $this->actingAs($owner)->post('/verify-email', ['code' => $code, 'user_id' => $other->id])->assertSessionHasNoErrors();
        $this->assertTrue($owner->fresh()->hasVerifiedEmail());
        $this->assertNull($owner->fresh()->email_code_digest);
        $this->assertFalse($other->fresh()->hasVerifiedEmail());
        $this->post('/verify-email', ['code' => $code])->assertSessionHasErrors('code');
        $this->get('/settings')->assertOk()->assertSee($owner->email)->assertSee('Verificat');
    }

    public function test_resend_cooldown_exhaustion_expiry_and_hourly_limit(): void
    {
        Notification::fake();
        $this->freezeTime();
        $user = User::factory()->unverified()->create()->refresh();
        $this->actingAs($user)->post('/verify-email/resend');
        $first = Notification::sent($user, VerifyEmailCode::class)->sole()->code;
        $this->travel(59)->seconds();
        $this->post('/verify-email/resend');
        Notification::assertSentToTimes($user, VerifyEmailCode::class, 1);
        $this->travel(1)->seconds();
        $this->post('/verify-email/resend');
        $second = Notification::sent($user, VerifyEmailCode::class)->last()->code;
        $this->assertNotSame($first, $second);
        foreach (['Cod incorect. Mai ai 2 încercări.', 'Cod incorect. Mai ai o încercare.', 'Ai epuizat cele 3 încercări. Solicită un cod nou.'] as $error) {
            $this->post('/verify-email', ['code' => $first])->assertSessionHasErrors(['code' => $error]);
        }
        $this->post('/verify-email', ['code' => $second])->assertSessionHasErrors('code');
        $this->travel(60)->seconds();
        $this->post('/verify-email/resend');
        $third = Notification::sent($user, VerifyEmailCode::class)->last()->code;
        $this->travel(10)->minutes();
        $this->post('/verify-email', ['code' => $third])->assertSessionHasErrors(['code' => 'Codul a expirat. Solicită un cod nou.']);
        foreach ([4, 5, 5] as $expected) {
            $this->travel(60)->seconds();
            $this->post('/verify-email/resend');
            Notification::assertSentToTimes($user, VerifyEmailCode::class, $expected);
        }
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_disabled_account_cannot_use_or_request_a_code(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create()->refresh();
        $this->actingAs($user)->post('/verify-email/resend');
        $code = Notification::sent($user, VerifyEmailCode::class)->sole()->code;
        $user->forceFill(['status' => 'disabled'])->save();
        $this->actingAs($user->fresh())->post('/verify-email', ['code' => $code])->assertForbidden();
        $this->actingAs($user->fresh())->post('/verify-email/resend')->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
        $this->assertFalse($user->fresh()->isActive());
        Notification::assertSentToTimes($user, VerifyEmailCode::class, 1);
    }

    public function test_email_change_requires_verification_at_the_new_address_and_revokes_old_reset_link(): void
    {
        Notification::fake();
        $user = User::factory()->create()->refresh();
        $this->post('/forgot-password', ['email' => $user->email]);
        Notification::assertSentTo($user, ResetAccountPassword::class);
        $this->actingAs($user)->post('/settings/profile', [
            'email' => 'new@example.test', 'name' => $user->name, 'current_password' => 'password',
        ])->assertRedirectToRoute('aura.verify-email');
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        $user->refresh();
        $this->assertFalse($user->hasVerifiedEmail());
        $this->get('/settings')->assertRedirectToRoute('aura.verify-email');
        $code = Notification::sent($user, VerifyEmailCode::class)->sole()->code;
        $this->post('/verify-email', ['code' => $code])->assertSessionHasNoErrors();
        $this->get('/settings')->assertOk()->assertSee('new@example.test');
    }

    #[TestWith(['user', '', '/admin', 'aura.login'])]
    #[TestWith(['super_admin', '/admin', '', 'aura.admin.login'])]
    public function test_reset_tokens_are_role_scoped_single_use_and_invalidate_sessions(string $role, string $prefix, string $other, string $login): void
    {
        Notification::fake();
        $user = User::factory()->create(['role' => $role, 'email' => 'Mixed.Case@example.test'])->refresh();
        $sessionHash = Auth::guard()->hashPasswordForCookie($user->getAuthPassword());
        $this->post($prefix.'/forgot-password', ['email' => strtoupper($user->email)])->assertSessionHas('status');
        $notice = Notification::sent($user, ResetAccountPassword::class)->sole();
        $url = $notice->toMail($user)->viewData['resetUrl'];
        $this->assertStringStartsWith(rtrim(config('app.url'), '/').'/reset-password/', $url);
        $this->get($url)->assertOk()->assertHeader('Referrer-Policy', 'no-referrer')->assertSee('action="'.url($prefix.'/reset-password').'"', false);
        $payload = $this->passwordPayload($user, $notice);
        $this->post($other.'/reset-password', $payload + ['area' => $prefix, 'role' => $role])->assertSessionHasErrors('email');
        $this->post($prefix.'/reset-password', $payload)->assertRedirectToRoute($login);
        $this->assertTrue(Hash::check($payload['password'], $user->fresh()->password));
        $this->assertNotSame($user->getRememberToken(), $user->fresh()->getRememberToken());
        $this->travel(61)->seconds();
        $this->post($prefix.'/reset-password', $payload)->assertSessionHasErrors('email');
        $this->actingAs($user->fresh())->withSession(['password_hash_web' => $sessionHash])->get('/settings')->assertRedirect();
        $this->assertGuest();
    }

    public function test_token_issued_before_a_role_change_cannot_be_redeemed_in_either_area(): void
    {
        Notification::fake();
        $user = User::factory()->create()->refresh();
        $this->post('/forgot-password', ['email' => $user->email]);
        $payload = $this->passwordPayload($user, Notification::sent($user, ResetAccountPassword::class)->sole());
        $user->forceFill(['role' => 'super_admin'])->save();
        $this->post('/admin/reset-password', $payload)->assertSessionHasErrors('email');
        $this->post('/reset-password', $payload)->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_reset_requests_are_generic_and_throttled_and_expired_tokens_fail(): void
    {
        Notification::fake();
        $this->freezeTime();
        $user = User::factory()->create()->refresh();
        $disabled = User::factory()->create(['status' => 'disabled']);
        $admin = User::factory()->create(['role' => 'super_admin']);
        $message = 'Dacă adresa este înregistrată, vei primi instrucțiuni pentru resetarea parolei.';
        foreach ([$disabled->email, $admin->email, 'missing@example.test'] as $email) {
            $this->post('/forgot-password', ['email' => $email])->assertSessionHas('status', $message);
        }
        Notification::assertNothingSent();
        $this->post('/forgot-password', ['email' => $user->email])->assertTooManyRequests();
        $this->travel(61)->seconds();
        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHas('status', $message);
        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHas('status', $message);
        $token = Notification::sent($user, ResetAccountPassword::class)->sole();
        $this->travel(61)->minutes();
        $this->post('/reset-password', $this->passwordPayload($user, $token))->assertSessionHasErrors('email');
        $this->artisan('auth:clear-resets')->assertSuccessful();
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        $this->post('/forgot-password', ['email' => $user->email]);
        $newToken = Notification::sent($user, ResetAccountPassword::class)->last();
        $user->forceFill(['status' => 'disabled'])->save();
        $this->post('/reset-password', $this->passwordPayload($user, $newToken))->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    #[TestWith(['user', 'aura.login.store', 'aura.dashboard'])]
    #[TestWith(['super_admin', 'aura.admin.login.store', 'aura.admin.index'])]
    public function test_distributed_failures_lock_for_exactly_one_day_after_ownership_proof(string $role, string $login, string $destination): void
    {
        Notification::fake();
        $this->freezeTime();
        $user = User::factory()->create(['role' => $role]);
        $wrong = ['email' => strtoupper($user->email), 'password' => 'wrong-password'];
        $correct = ['email' => $user->email, 'password' => 'password'];
        foreach (['192.0.2.1', '192.0.2.2'] as $ip) {
            $this->withServerVariables(['REMOTE_ADDR' => $ip])->post(route($login), $wrong)->assertSessionHasErrors(['email' => LoginAttempts::INVALID]);
        }
        $proof = Notification::sent($user, VerifyEmailCode::class)->sole();
        $this->assertTrue($proof->forLogin);
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.3'])->post(route($login), $wrong + ['answer' => 'fabricated'])->assertSessionHasErrors('email');
        $this->post(route($login), $wrong + ['answer' => $proof->code])->assertSessionHasErrors(['email' => LoginAttempts::LOCKED]);
        Cache::flush();
        $this->travel(23)->hours();
        $this->post(route($login), $correct)->assertSessionHasErrors(['email' => LoginAttempts::LOCKED]);
        $this->assertGuest();
        $this->travel(1)->hours();
        $this->post(route($login), $correct)->assertRedirectToRoute($destination);
        $this->assertAuthenticatedAs($user);
    }

    public function test_a_locked_account_and_an_unknown_address_give_the_same_error_without_proof(): void
    {
        $user = User::factory()->create(['login_failures' => 3, 'login_locked_until' => now()->addDay()]);
        foreach ([$user->email, 'absent@example.test'] as $email) {
            $this->post('/login', ['email' => $email, 'password' => 'incorrect'])
                ->assertSessionHasErrors(['email' => LoginAttempts::INVALID])
                ->assertSessionHas('login_challenge', true);
        }
    }

    public function test_an_existing_unverified_admin_must_verify_before_admin_access(): void
    {
        Notification::fake();
        $admin = User::factory()->unverified()->create(['role' => 'super_admin'])->refresh();
        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'password'])->assertRedirectToRoute('aura.verify-email');
        $this->get('/admin')->assertRedirectToRoute('aura.verify-email');
        $code = Notification::sent($admin, VerifyEmailCode::class)->sole()->code;
        $this->post('/verify-email', ['code' => $code])->assertSessionHasNoErrors();
        $this->get('/admin')->assertOk();
    }

    public function test_successful_login_before_lockout_clears_consecutive_failures(): void
    {
        Notification::fake();
        $user = User::factory()->create()->refresh();
        $wrong = ['email' => $user->email, 'password' => 'incorrect'];
        $this->post('/login', $wrong)->assertSessionHasErrors(['email' => LoginAttempts::INVALID]);
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirectToRoute('aura.dashboard');
        $this->post('/logout');
        $this->post('/login', $wrong)->assertSessionHasErrors(['email' => LoginAttempts::INVALID]);
        Notification::assertNothingSent();
    }

    public function test_both_romanian_messages_render_and_are_addressed_by_the_mail_channel(): void
    {
        config(['mail.from.address' => 'aura@example.test', 'mail.from.name' => 'AURA']);
        $user = User::factory()->create()->refresh();
        $user->notifyNow(new VerifyEmailCode('012345'));
        $user->notifyNow(new ResetAccountPassword('test-only-token'));
        $messages = Mail::mailer()->getSymfonyTransport()->messages();
        $this->assertCount(2, $messages);
        foreach ($messages as $message) {
            $email = $message->getOriginalMessage();
            $this->assertSame('AURA', $email->getFrom()[0]->getName());
            $this->assertSame($user->email, $email->getTo()[0]->getAddress());
        }
        $this->assertStringContainsString('012345', $messages->first()->getOriginalMessage()->getHtmlBody());
        $this->assertStringContainsString('60 de minute', $messages->last()->getOriginalMessage()->getHtmlBody());
    }

    #[TestWith(['smtp.gmail.com', 'sender@example.test'])]
    #[TestWith(['smtp.resend.com', 'resend'])]
    public function test_provider_settings_resolve_the_same_native_tls_transport(string $host, string $username): void
    {
        config([
            'mail.mailers.smtp.host' => $host, 'mail.mailers.smtp.username' => $username,
            'mail.mailers.smtp.port' => 465, 'mail.mailers.smtp.scheme' => 'smtps',
            'mail.mailers.smtp.url' => null,
        ]);
        $transport = Mail::mailer('smtp')->getSymfonyTransport();
        $this->assertInstanceOf(EsmtpTransport::class, $transport);
        $this->assertSame($host, $transport->getStream()->getHost());
        $this->assertSame(465, $transport->getStream()->getPort());
        $this->assertTrue($transport->getStream()->isTLS());
        $this->assertSame($username, $transport->getUsername());
    }

    public function test_unsafe_transport_and_delivery_exception_do_not_log_secrets(): void
    {
        $user = User::factory()->unverified()->create()->refresh();
        $notice = new VerifyEmailCode('012345');
        $mail = app(AccountMail::class);
        $this->app->instance(\Psr\Log\LoggerInterface::class, new \Psr\Log\NullLogger);
        Log::shouldReceive('warning')->once()->with('Expedierea mesajelor de cont necesită un transport SMTP configurat.');
        config(['mail.default' => 'log']);
        $this->assertFalse($mail->send($user, $notice));
        config(['mail.default' => 'smtp']);
        Notification::shouldReceive('sendNow')->once()->andThrow(new RuntimeException('secret-smtp-diagnostic'));
        Log::shouldReceive('error')->once()->with('Expedierea mesajului de cont a eșuat.', ['exception_type' => RuntimeException::class]);
        $this->assertFalse($mail->send($user, $notice));
    }

    /** @return array<string, string> */
    private function passwordPayload(User $user, ResetAccountPassword $notification): array
    {
        $url = $notification->toMail($user)->viewData['resetUrl'];
        $token = rawurldecode(basename(parse_url($url, PHP_URL_PATH)));

        return ['email' => strtolower($user->email), 'token' => $token, 'password' => 'New-password-456', 'password_confirmation' => 'New-password-456'];
    }
}
