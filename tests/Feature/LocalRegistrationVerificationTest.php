<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\VerifyEmailCode;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class LocalRegistrationVerificationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_local_registration_without_email_verification_creates_a_verified_account_without_a_code(): void
    {
        $this->app->instance('env', 'local');
        config()->set('auth.email_verification', false);
        Notification::fake();

        $this->withSession(['_token' => 'registration-test-token'])
            ->post('/register', $this->registration('local@example.test') + ['_token' => 'registration-test-token'])
            ->assertRedirectToRoute('aura.dashboard');

        $user = User::where('email', 'local@example.test')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNull($user->email_code_digest);
        $this->assertNull($user->email_code_sent_at);
        Notification::assertNothingSent();
        $this->get('/dashboard')->assertOk();
    }

    public function test_registration_with_email_verification_enabled_issues_a_code(): void
    {
        config()->set('auth.email_verification', true);
        Notification::fake();

        $this->post('/register', $this->registration('verified-flow@example.test'))
            ->assertRedirectToRoute('aura.verify-email');

        $user = User::where('email', 'verified-flow@example.test')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->email_verified_at);
        $this->assertNotNull($user->email_code_digest);
        $this->assertNotNull($user->email_code_sent_at);
        Notification::assertSentTo($user, VerifyEmailCode::class);
    }

    #[TestWith(['dev'])]
    #[TestWith(['production'])]
    public function test_non_local_registration_requires_email_verification_even_when_disabled(string $environment): void
    {
        $this->app->instance('env', $environment);
        config()->set('auth.email_verification', false);

        $this->withSession(['_token' => 'registration-test-token'])
            ->post('/register', $this->registration($environment.'@example.test') + ['_token' => 'registration-test-token'])
            ->assertRedirectToRoute('aura.verify-email');

        $user = User::where('email', $environment.'@example.test')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->email_verified_at);
        $this->assertNotNull($user->email_code_digest);
        $this->assertNotNull($user->email_code_sent_at);
    }

    /** @return array{name: string, email: string, terms: string, password: string, password_confirmation: string} */
    private function registration(string $email): array
    {
        return [
            'name' => 'Cont local de test',
            'email' => $email,
            'terms' => '1',
            'password' => 'Test-password-123',
            'password_confirmation' => 'Test-password-123',
        ];
    }
}
