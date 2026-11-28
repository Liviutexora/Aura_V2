<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\User;
use App\Models\UserMembership;
use App\Services\UserHeaderStatus;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class UserHeaderTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_one_header_shows_the_same_real_membership_on_every_user_page(): void
    {
        config()->set('pwa.enabled', false);
        $user = User::factory()->create(['name' => 'Ana Exemplu', 'status' => 'active']);
        $this->membership($user, 'Plan Comunitate', 'manual', now()->addDays(10));

        foreach (['/dashboard', '/wallet', '/subscriptions', '/achievements', '/settings', '/integrations', '/projects'] as $path) {
            $html = $this->actingAs($user)->get($path)->assertOk()->getContent();

            $this->assertSame(1, substr_count($html, 'class="aura-user-header"'), $path);
            $this->assertSame(1, substr_count($html, 'class="mobile-nav aura-user-mobile-nav"'), $path);
            $this->assertStringContainsString('data-pwa-header-status title="Plan Comunitate">Plan Comunitate</a>', $html, $path);
            $this->assertStringContainsString('class="avatar" aria-label="Setări cont">A</a>', $html, $path);
            $this->assertStringNotContainsString('data-pwa-header-install', $html, $path);
        }
    }

    public function test_active_account_without_membership_has_gold_outline_and_subscription_link(): void
    {
        config()->set('pwa.enabled', false);
        $user = User::factory()->create(['status' => 'active']);

        $this->actingAs($user)->get('/dashboard')->assertOk()
            ->assertSee('aura-header-indicator--basic', false)
            ->assertSee('data-pwa-header-status title="Cont activ">Cont activ</a>', false)
            ->assertSee('href="'.route('aura.subscriptions').'"', false);
    }

    #[TestWith(['Standard', 'manual', 'Standard'])]
    #[TestWith(['Premium', 'manual', 'Premium'])]
    #[TestWith(['Business', 'manual', 'Business'])]
    #[TestWith(['Plan Comunitate', 'manual', 'Plan Comunitate'])]
    #[TestWith(['Standard', 'trial', 'Trial'])]
    public function test_active_membership_shows_its_real_name_or_trial(string $planName, string $source, string $expected): void
    {
        config()->set('pwa.enabled', false);
        $user = User::factory()->create(['status' => 'active']);
        $this->membership($user, $planName, $source, now()->addDays(7));

        $this->actingAs($user)->get('/dashboard')->assertOk()
            ->assertSee('aura-header-indicator--active', false)
            ->assertSee('data-pwa-header-status title="'.$expected.'">'.$expected.'</a>', false);
    }

    public function test_expiration_warning_starts_at_exactly_72_hours(): void
    {
        config()->set('pwa.enabled', false);
        $this->freezeTime();
        $user = User::factory()->create(['status' => 'active']);
        $membership = $this->membership($user, 'Business', 'manual', now()->addHours(72)->addSecond());

        $this->actingAs($user)->get('/dashboard')->assertOk()->assertSee('aura-header-indicator--active', false);

        $membership->update(['ends_at' => now()->addHours(72)]);
        $this->get('/dashboard')->assertOk()->assertSee('aura-header-indicator--warning', false)
            ->assertSee('data-pwa-header-status title="Business">Business</a>', false);
    }

    public function test_expired_membership_is_never_shown_as_active(): void
    {
        config()->set('pwa.enabled', false);
        $this->freezeTime();
        $user = User::factory()->create(['status' => 'active']);
        $this->membership($user, 'Premium', 'manual', now()->subSecond());

        $this->actingAs($user)->get('/dashboard')->assertOk()
            ->assertSee('aura-header-indicator--danger', false)
            ->assertSee('data-pwa-header-status title="Abonament expirat">Abonament expirat</a>', false)
            ->assertDontSee('title="Premium">Premium</a>', false);
    }

    #[TestWith(['suspended', 'Cont suspendat'])]
    #[TestWith(['disabled', 'Cont inactiv'])]
    public function test_account_status_takes_priority_over_membership_and_installation(string $status, string $expected): void
    {
        $user = User::factory()->create(['status' => $status]);
        $this->membership($user, 'Business', 'manual', now()->addDays(7));

        $indicator = app(UserHeaderStatus::class)->for($user);

        $this->assertSame($expected, $indicator['label']);
        $this->assertSame('danger', $indicator['tone']);
        $this->assertFalse($indicator['linkable']);
        $this->assertFalse($indicator['installable']);

        config()->set('pwa.enabled', true);
        $this->actingAs($user);
        $html = Blade::render('<x-aura.user-header />');
        $this->assertStringContainsString('data-pwa-header-status>'.$expected.'</span>', $html);
        $this->assertStringNotContainsString('data-pwa-header-install', $html);
        $this->assertStringNotContainsString('data-pwa-header-status title=', $html);
    }

    private function membership(User $user, string $name, string $source, \DateTimeInterface $endsAt): UserMembership
    {
        $plan = Plan::factory()->create(['name' => $name]);

        return UserMembership::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'source' => $source,
            'terms' => $plan->terms(),
            'starts_at' => now()->subDay(),
            'ends_at' => $endsAt,
        ]);
    }
}
