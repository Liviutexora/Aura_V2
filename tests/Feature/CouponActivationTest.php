<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\SubscriptionPromoCode;
use App\Models\User;
use App\Models\UserMembership;
use App\Notifications\VerifyEmailCode;
use App\Services\MembershipService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class CouponActivationTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** @return array{code:string, plan_id:int, timing:string, request_key:string} */
    private function coupon(Plan $plan): array
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $issued = app(MembershipService::class)->issueCode($admin, $plan->id, 13, null, now()->addWeek()->toImmutable(), (string) Str::uuid());

        return ['code' => $issued['code'], 'plan_id' => $plan->id, 'timing' => 'now', 'request_key' => (string) Str::uuid()];
    }

    public function test_activation_requires_matching_plan_and_uses_only_coupon_days(): void
    {
        $this->freezeSecond();
        $plan = Plan::factory()->create();
        $owner = User::factory()->create(['status' => 'active']);
        $payload = $this->coupon($plan) + ['duration_days' => 900, 'interval' => 'quarterly'];
        $this->postJson('/subscriptions/redeem', $payload)->assertUnauthorized();
        $this->actingAs($owner)->postJson('/subscriptions/redeem', array_replace($payload, ['plan_id' => $plan->id + 1]))
            ->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->postJson('/subscriptions/redeem', array_replace($payload, ['code' => 'X00000000']))->assertUnprocessable();
        $this->assertNull(SubscriptionPromoCode::sole()->redeemed_at);

        $this->postJson('/subscriptions/redeem', $payload)->assertCreated();
        $this->postJson('/subscriptions/redeem', $payload)->assertConflict();
        $this->postJson('/subscriptions/redeem', array_replace($payload, ['request_key' => (string) Str::uuid()]))->assertUnprocessable();
        $period = UserMembership::sole();
        $this->assertTrue($period->ends_at->equalTo(now()->addDays(13)));
        $this->assertTrue($period->starts_at->equalTo(now()));
        $this->assertDatabaseHas('subscription_events', ['actor_id' => $owner->id, 'promo_code_id' => SubscriptionPromoCode::sole()->id, 'membership_id' => $period->id]);
        $extension = $this->coupon($plan);
        $this->postJson('/subscriptions/redeem', $extension)->assertCreated();
        $this->assertTrue(UserMembership::latest('id')->firstOrFail()->ends_at->equalTo(now()->addDays(26)));
    }

    #[TestWith(['now'])]
    #[TestWith(['period_end'])]
    public function test_switch_preserves_snapshots_and_requires_current_confirmation_for_immediate_loss(string $timing): void
    {
        $this->freezeSecond();
        $old = Plan::factory()->create(['access_level' => 8]);
        $new = Plan::factory()->create(['access_level' => 1]);
        $owner = User::factory()->create(['status' => 'active']);
        $period = UserMembership::create(['user_id' => $owner->id, 'plan_id' => $old->id, 'source' => 'manual', 'terms' => $old->terms(),
            'starts_at' => now()->subDay(), 'ends_at' => now()->addDays(3)]);
        $snapshot = $period->fresh()->terms;
        $payload = array_replace($this->coupon($new), ['timing' => $timing]);
        $this->actingAs($owner);
        if ($timing === 'now') {
            $this->postJson('/subscriptions/redeem', $payload)->assertUnprocessable();
            $payload['confirm_replace_membership_id'] = $period->id;
            $this->postJson('/subscriptions/redeem', $payload + ['confirm_replace_ends_at' => now()->timestamp])->assertUnprocessable();
            $this->assertNull(SubscriptionPromoCode::sole()->redeemed_at);
            $payload['confirm_replace_ends_at'] = $period->ends_at->timestamp;
        }
        $this->postJson('/subscriptions/redeem', $payload)->assertCreated();
        $start = $timing === 'now' ? now() : now()->addDays(3);
        $next = UserMembership::latest('id')->firstOrFail();
        $this->assertTrue($next->starts_at->equalTo($start));
        $this->assertTrue($next->ends_at->equalTo($start->copy()->addDays(13)));
        $this->assertTrue($period->fresh()->ends_at->equalTo($start));
        $this->assertSame($snapshot, $period->fresh()->terms);
        if ($timing === 'period_end') {
            $other = $this->coupon($old);
            $this->postJson('/subscriptions/redeem', $other)->assertUnprocessable()->assertJsonValidationErrors('timing');
            $this->assertNull(SubscriptionPromoCode::latest('id')->firstOrFail()->redeemed_at);
            $this->assertSame($next->starts_at->timestamp, $next->fresh()->starts_at->timestamp);
            $this->assertDatabaseCount('user_memberships', 2);
        }
    }

    public function test_trial_requires_csrf_and_is_available_once_even_after_expiry(): void
    {
        $plan = Plan::factory()->create(['trial_days' => 4]);
        $user = User::factory()->create(['status' => 'active']);
        $this->actingAs($user)->get('/subscriptions')->assertOk()->assertSee('Testează gratuit');
        $env = $this->app['env'];
        $this->app['env'] = 'production';
        try {
            foreach (['trial', 'redeem'] as $action) {
                $this->postJson('/subscriptions/'.$action, [])->assertStatus(419);
            }
        } finally {
            $this->app['env'] = $env;
        }
        $trial = ['plan_id' => $plan->id, 'request_key' => (string) Str::uuid()];
        $this->postJson('/subscriptions/trial', $trial)->assertCreated();
        $this->postJson('/subscriptions/trial', $trial)->assertConflict();
        $this->postJson('/subscriptions/trial', array_replace($trial, ['request_key' => (string) Str::uuid()]))->assertUnprocessable();
        $this->travel(5)->days();
        $this->postJson('/subscriptions/trial', array_replace($trial, ['request_key' => (string) Str::uuid()]))->assertUnprocessable();
        $this->assertDatabaseCount('user_memberships', 1);
    }

    public function test_invalid_attempts_are_throttled(): void
    {
        $this->actingAs(User::factory()->create(['status' => 'active']));
        $payload = ['plan_id' => 1, 'timing' => 'now', 'code' => 'X00000000', 'request_key' => (string) Str::uuid()];
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/subscriptions/redeem', $payload)->assertUnprocessable();
        }
        $this->postJson('/subscriptions/redeem', $payload)->assertTooManyRequests();
        $this->assertDatabaseCount('user_memberships', 0);
    }

    public function test_ineligible_accounts_cannot_consume_a_coupon_and_an_active_period_blocks_a_first_trial(): void
    {
        $plan = Plan::factory()->create(['trial_days' => 3]);
        $payload = $this->coupon($plan);
        $this->actingAs(User::factory()->create(['status' => 'suspended']))
            ->postJson('/subscriptions/redeem', $payload)->assertForbidden();
        $this->actingAs(User::factory()->unverified()->create(['status' => 'active']))
            ->postJson('/subscriptions/redeem', $payload)->assertForbidden();
        $this->assertNull(SubscriptionPromoCode::sole()->redeemed_at);
        $owner = User::factory()->create(['status' => 'active']);
        $this->actingAs($owner)->postJson('/subscriptions/redeem', $payload)->assertCreated();
        $this->postJson('/subscriptions/trial', ['plan_id' => $plan->id, 'request_key' => (string) Str::uuid()])
            ->assertUnprocessable()->assertJsonValidationErrors('trial');
        $this->assertNull($owner->fresh()->trial_used_at);
        $this->assertDatabaseCount('user_memberships', 1);
    }

    #[TestWith([true])]
    #[TestWith([false])]
    public function test_landing_selection_survives_login_or_registration_and_verification(bool $register): void
    {
        Notification::fake();
        $plan = Plan::factory()->create(['name' => 'Plan din catalog', 'access_level' => 100]);
        $inactive = Plan::factory()->create(['name' => 'Plan retras', 'is_active' => false]);
        $this->get('/')->assertOk()->assertSee($plan->name)->assertDontSee($inactive->name)->assertSee(route('aura.subscriptions.select', $plan));
        $this->get(route('aura.subscriptions.select', $inactive))->assertNotFound();
        $this->get(route('aura.subscriptions.select', $plan))->assertRedirectToRoute('aura.register');
        if ($register) {
            $this->post('/register', ['name' => 'Ana', 'email' => 'ana@example.org', 'phone' => '0712345678', 'password' => 'StrongPass!123',
                'password_confirmation' => 'StrongPass!123', 'terms' => '1'])->assertRedirectToRoute('aura.verify-email');
            $user = User::where('email', 'ana@example.org')->sole();
            $code = Notification::sent($user, VerifyEmailCode::class)->sole()->code;
            $this->post('/verify-email', ['code' => $code])->assertRedirectToRoute('aura.subscriptions');
        } else {
            $user = User::factory()->create(['status' => 'active', 'password' => 'StrongPass!123']);
            $this->post('/login', ['email' => $user->email, 'password' => 'StrongPass!123'])->assertRedirectToRoute('aura.subscriptions');
        }
        $this->get('/subscriptions')->assertOk()->assertSee('data-selected="'.$plan->id.'"', false)->assertSee('Activează prin cupon');
        $this->get(route('aura.subscriptions.select', $plan))->assertRedirectToRoute('aura.subscriptions');
        $this->get('/subscriptions')->assertOk()->assertSee('data-selected="'.$plan->id.'"', false);
        $this->assertDatabaseCount('user_memberships', 0);
    }
}
