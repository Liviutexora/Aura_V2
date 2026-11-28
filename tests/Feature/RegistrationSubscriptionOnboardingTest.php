<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Opportunity;
use App\Models\Plan;
use App\Models\Project;
use App\Models\User;
use App\Models\UserMembership;
use App\Notifications\VerifyEmailCode;
use App\Services\MembershipService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class RegistrationSubscriptionOnboardingTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[TestWith(['0712345678', '+40712345678'])]
    #[TestWith(['+44 7911 123456', '+447911123456'])]
    public function test_registration_normalizes_and_stores_the_phone(string $input, string $expected): void
    {
        Notification::fake();
        $existing = User::factory()->create(['phone' => null]);

        $this->post('/register', $this->registration($input))->assertRedirectToRoute('aura.verify-email');

        $this->assertDatabaseHas('users', ['email' => 'new@example.test', 'phone' => $expected]);
        $this->assertNull($existing->fresh()->phone);
    }

    #[TestWith([''])]
    #[TestWith(['071234567'])]
    #[TestWith(['004407911123456'])]
    #[TestWith(['+40712345678abc'])]
    public function test_registration_rejects_missing_or_invalid_phone_numbers(string $phone): void
    {
        $this->post('/register', $this->registration($phone))->assertSessionHasErrors('phone');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_verified_registration_without_a_preselected_plan_reaches_the_persistent_choice_and_trial(): void
    {
        Notification::fake();
        $trialPlan = Plan::factory()->create(['name' => 'Plan trial', 'trial_days' => 30]);
        $inactivePlan = Plan::factory()->create(['name' => 'Plan inactiv', 'is_active' => false]);

        $this->post('/register', $this->registration('0712345678'))->assertRedirectToRoute('aura.verify-email');
        $user = User::where('email', 'new@example.test')->sole();
        $code = Notification::sent($user, VerifyEmailCode::class)->sole()->code;
        $this->post('/verify-email', ['code' => $code])->assertRedirectToRoute('aura.subscriptions');
        $this->get('/subscriptions')->assertOk()->assertSee('Pasul 2 din 2')->assertSee($trialPlan->name)->assertDontSee($inactivePlan->name);
        $this->get('/dashboard')->assertOk()->assertSee('Continuă alegerea abonamentului');
        $this->postJson('/subscriptions/selection', ['plan_id' => $inactivePlan->id])->assertNotFound();
        $this->postJson('/subscriptions/trial', ['plan_id' => $trialPlan->id, 'request_key' => (string) Str::uuid()])
            ->assertUnprocessable()->assertJsonValidationErrors('plan_id');
        $this->assertDatabaseCount('user_memberships', 0);

        $this->postJson('/subscriptions/selection', ['plan_id' => $trialPlan->id])->assertOk();
        $this->get('/subscriptions')->assertOk()->assertSee('data-selected="'.$trialPlan->id.'"', false)->assertSee('trialul gratuit de 30 zile');
        $this->get('/subscriptions')->assertOk()->assertSee('data-selected="'.$trialPlan->id.'"', false);
        $trialPlan->update(['is_active' => false]);
        $this->get('/subscriptions')->assertOk()->assertSee('Planul ales anterior nu mai este disponibil');
        $trialPlan->update(['is_active' => true]);
        $this->postJson('/subscriptions/trial', ['plan_id' => $trialPlan->id, 'request_key' => (string) Str::uuid()])->assertCreated();
        $this->assertDatabaseCount('user_memberships', 1);
        $this->assertFalse(session()->has('subscriptions.onboarding'));
        $this->get('/dashboard')->assertOk()->assertDontSee('Continuă alegerea abonamentului');
    }

    public function test_selected_plan_controls_trial_visibility_and_coupon_activation(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $trialPlan = Plan::factory()->create(['name' => 'Plan gratuit', 'trial_days' => 30]);
        $paidPlan = Plan::factory()->create(['name' => 'Plan cu cupon', 'trial_days' => 0]);
        $admin = User::factory()->create(['role' => 'super_admin']);
        $code = app(MembershipService::class)->issueCode($admin, $paidPlan->id, 30, null, now()->addWeek()->toImmutable(), (string) Str::uuid())['code'];
        $this->actingAs($user)->withSession(['subscriptions.onboarding' => true]);

        $this->postJson('/subscriptions/selection', ['plan_id' => $trialPlan->id])->assertOk();
        $trialResponse = $this->get('/subscriptions')->assertOk()->assertSee('data-selected-trial');
        $this->assertDoesNotMatchRegularExpression('/data-selected-trial\s+hidden/', $trialResponse->getContent());
        $this->postJson('/subscriptions/selection', ['plan_id' => $paidPlan->id])->assertOk();
        $paidResponse = $this->get('/subscriptions')->assertOk()->assertSee('data-selected-trial');
        $this->assertMatchesRegularExpression('/data-selected-trial\s+hidden/', $paidResponse->getContent());
        $this->postJson('/subscriptions/trial', ['plan_id' => $trialPlan->id, 'request_key' => (string) Str::uuid()])
            ->assertUnprocessable()->assertJsonValidationErrors('plan_id');
        $this->postJson('/subscriptions/redeem', [
            'plan_id' => $paidPlan->id, 'code' => 'INVALID00', 'timing' => 'now', 'request_key' => (string) Str::uuid(),
        ])->assertUnprocessable();
        $this->assertDatabaseCount('user_memberships', 0);
        $this->postJson('/subscriptions/redeem', [
            'plan_id' => $paidPlan->id, 'code' => $code, 'timing' => 'now', 'request_key' => (string) Str::uuid(),
        ])->assertCreated();
        $this->assertSame($paidPlan->id, UserMembership::sole()->plan_id);
        $this->assertFalse(session()->has('subscriptions.onboarding'));
    }

    public function test_registration_can_be_resumed_after_logout_without_granting_a_membership(): void
    {
        Notification::fake();
        $plan = Plan::factory()->create(['name' => 'Plan de reluat']);

        $this->post('/register', $this->registration('0712345678'))->assertRedirectToRoute('aura.verify-email');
        $user = User::where('email', 'new@example.test')->sole();
        $code = Notification::sent($user, VerifyEmailCode::class)->sole()->code;
        $this->post('/verify-email', ['code' => $code])->assertRedirectToRoute('aura.subscriptions');
        $this->post('/logout')->assertRedirectToRoute('aura.login');
        $this->post('/login', ['email' => $user->email, 'password' => 'StrongPass!123'])->assertRedirect();
        $this->get('/dashboard')->assertOk()->assertSee('Continuă alegerea abonamentului');
        $this->get('/subscriptions')->assertOk()->assertSee($plan->name);
        $this->assertDatabaseCount('user_memberships', 0);
        $this->assertModelExists($user);
    }

    public function test_an_accepted_project_cannot_start_without_a_current_membership(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $category = Category::create(['name' => 'Test', 'slug' => 'test', 'visibility' => 'all', 'status' => 'active']);
        $opportunity = Opportunity::create([
            'category_id' => $category->id, 'title' => 'Oportunitate test', 'summary' => 'Descriere',
            'provider' => 'Furnizor', 'terms' => 'Termeni', 'difficulty' => 'Ușor', 'estimated_minutes' => 10,
            'starts_at' => now()->subDay(), 'status' => 'published', 'created_by' => $user->id,
            'updated_by' => $user->id, 'reward_type' => 'fixed', 'reward_unit' => 'money',
            'reward_amount_minor' => 100, 'reward_currency' => 'RON', 'reward_label' => '1 leu', 'payer' => 'Furnizor',
        ]);
        $project = Project::create([
            'user_id' => $user->id, 'opportunity_id' => $opportunity->id, 'title' => 'Proiect test',
            'source' => 'self', 'created_by' => $user->id, 'status' => 'accepted', 'accepted_at' => now(),
            'opportunity_version' => 1, 'offer_snapshot' => [], 'eligibility_snapshot' => [],
        ]);

        $this->actingAs($user)->post('/projects/'.$project->id.'/start')->assertSessionHasErrors('membership');

        $this->assertSame('accepted', $project->fresh()->status);
        $plan = Plan::factory()->create(['trial_days' => 30]);
        app(MembershipService::class)->trial($user, $plan->id, (string) Str::uuid());
        $this->post('/projects/'.$project->id.'/start')->assertSessionHasNoErrors();
        $this->assertSame('active', $project->fresh()->status);
    }

    /** @return array{name: string, email: string, phone: string, password: string, password_confirmation: string, terms: string} */
    private function registration(string $phone): array
    {
        return [
            'name' => 'Cont nou', 'email' => 'new@example.test', 'phone' => $phone,
            'password' => 'StrongPass!123', 'password_confirmation' => 'StrongPass!123', 'terms' => '1',
        ];

    }
}
