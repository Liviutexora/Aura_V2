<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Opportunity;
use App\Models\Plan;
use App\Models\Project;
use App\Models\SubscriptionEvent;
use App\Models\User;
use App\Models\UserMembership;
use App\Services\SubscriptionAccess;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class SubscriptionInfrastructureTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[TestWith([7, 7, 7, []])]
    #[TestWith([null, 3, null, []])]
    #[TestWith([null, null, null, []])]
    #[TestWith([0, 0, 0, []])]
    #[TestWith([7, 0, 7, []])]
    #[TestWith([3, 6, 2, ['max_explorer_projects']])]
    #[TestWith([3, 2, 6, ['max_mentor_projects']])]
    #[TestWith([3, null, 2, ['max_explorer_projects']])]
    #[TestWith([3, 2, null, ['max_mentor_projects']])]
    #[TestWith([0, 1, 0, ['max_explorer_projects']])]
    /** @param list<string> $errors */
    public function test_plan_limits_are_validated_on_creation_and_editing(?int $total, ?int $explorer, ?int $mentor, array $errors): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $plan = Plan::factory()->create()->refresh();
        $limits = ['max_projects' => $total, 'max_explorer_projects' => $explorer, 'max_mentor_projects' => $mentor];
        $payload = array_replace($plan->terms(), $limits, ['is_active' => true, 'request_key' => (string) Str::uuid()]);
        unset($payload['version']);
        $count = Plan::count();
        $created = $this->actingAs($admin)->postJson('/admin/plans', $payload);
        $updated = $this->patchJson('/admin/plans/'.$plan->id, $limits + ['version' => 1, 'request_key' => (string) Str::uuid()]);
        if ($errors !== []) {
            $created->assertUnprocessable()->assertJsonValidationErrors($errors);
            $updated->assertUnprocessable()->assertJsonValidationErrors($errors);
            $this->assertDatabaseCount('plans', $count);
            $this->assertDatabaseCount('subscription_events', 0);
            $this->assertSame(5, $plan->fresh()->max_projects);

            return;
        }
        $created->assertCreated();
        $updated->assertOk();
        foreach ($limits as $field => $value) {
            $created->assertJsonPath($field, $value);
            $updated->assertJsonPath($field, $value);
        }
    }

    public function test_partial_edits_validate_the_combined_limits_and_html_forms_show_the_error(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $plan = Plan::factory()->create()->refresh();
        $this->actingAs($admin)->patchJson('/admin/plans/'.$plan->id, [
            'max_projects' => 2, 'version' => 1, 'request_key' => (string) Str::uuid(),
        ])->assertUnprocessable()->assertJsonValidationErrors(['max_explorer_projects']);
        $form = ['plan_form' => 1, 'name' => $plan->name, 'access_level' => 1, 'monthly_price' => '19,00',
            'quarterly_price' => '45.00', 'max_projects' => 3, 'max_explorer_projects' => 6, 'max_mentor_projects' => 2,
            'max_projects_unlimited' => 0, 'max_explorer_projects_unlimited' => 0, 'max_mentor_projects_unlimited' => 0,
            'trial_days' => 0, 'benefits_text' => '', 'is_active' => 1, 'request_key' => (string) Str::uuid()];
        $this->from('/admin/plans/create')->post('/admin/plans', $form)
            ->assertRedirect('/admin/plans/create');
        $this->withCookie(config('session.cookie'), session()->getId());
        $this->get('/admin/plans/create')->assertOk()->assertSee('Limita Explorator nu poate depăși limita totală de 3 proiecte.');
        $form['version'] = 1;
        $form['request_key'] = (string) Str::uuid();
        $this->from('/admin/plans/'.$plan->id.'/edit')->patch('/admin/plans/'.$plan->id, $form)
            ->assertRedirect('/admin/plans/'.$plan->id.'/edit')->assertSessionHasErrors('max_explorer_projects');
        $this->assertSame(1, $plan->fresh()->version);
    }

    #[TestWith([3, 4])]
    #[TestWith([6, 1])]
    public function test_mixed_projects_share_the_total_limit_at_acceptance(int $explorers, int $mentors): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $owner = User::factory()->create(['status' => 'active']);
        $plan = Plan::factory()->create(['max_projects' => 7, 'max_explorer_projects' => 7, 'max_mentor_projects' => 7]);
        UserMembership::create(['user_id' => $owner->id, 'plan_id' => $plan->id, 'source' => 'manual', 'terms' => $plan->terms(),
            'starts_at' => now()->subDay(), 'ends_at' => now()->addDay()]);
        $this->actingAs($owner);
        $completed = $this->quotaOpportunity($admin);
        $this->postJson('/opportunities/'.$completed->id.'/projects')->assertRedirect();
        $owner->projects()->sole()->update(['status' => 'completed', 'started_at' => now(), 'completed_at' => now()]);
        foreach (['explorer' => $explorers, 'mentor' => $mentors] as $type => $quantity) {
            for ($index = 0; $index < $quantity; $index++) {
                $offer = $this->quotaOpportunity($admin);
                $this->postJson('/opportunities/'.$offer->id.'/projects', ['participation_type' => $type])->assertRedirect();
            }
        }
        $extra = $this->quotaOpportunity($admin);
        $this->postJson('/opportunities/'.$extra->id.'/projects', ['participation_type' => 'explorer'])
            ->assertUnprocessable()->assertJsonValidationErrors('membership');
        $this->actingAs($admin)->postJson('/admin/projects', ['user_id' => $owner->id, 'opportunity_id' => $extra->id, 'participation_type' => 'mentor'])
            ->assertUnprocessable()->assertJsonValidationErrors('membership');
        $this->assertSame(7, $owner->projects()->whereIn('status', ['accepted', 'active'])->count());
    }

    private function quotaOpportunity(User $admin): Opportunity
    {
        $category = Category::firstOrCreate(['slug' => 'quota'], ['name' => 'Quota', 'status' => 'active', 'visibility' => 'all']);
        $offer = Opportunity::create(['category_id' => $category->id, 'title' => 'Proiect pentru verificarea cotei', 'summary' => 'Descriere',
            'provider' => 'Furnizor', 'terms' => 'Regulament', 'difficulty' => 'Ușor', 'estimated_minutes' => 10, 'status' => 'published',
            'starts_at' => now()->subDay(), 'reward_label' => 'Beneficiu', 'reward_type' => 'non_cash', 'reward_unit' => 'non_cash',
            'payer' => 'Furnizor', 'created_by' => $admin->id, 'updated_by' => $admin->id, 'participation_mode' => 'both']);
        $template = $offer->templates()->create(['title' => 'Confirmare', 'position' => 1, 'required_action' => 'Confirmă',
            'verification_method' => 'user_declaration', 'xp_points' => 0, 'assign_on_accept' => true, 'is_required' => true, 'status' => 'active']);
        $template->steps()->create(['title' => 'Pas', 'position' => 1, 'status' => 'active', 'required_action' => 'Confirmă', 'xp_points' => 0]);

        return $offer;
    }

    #[TestWith([0, 0, 0, 'explorer', false])]
    #[TestWith([null, 0, null, 'explorer', false])]
    #[TestWith([null, null, 0, 'mentor', false])]
    #[TestWith([null, null, null, 'mentor', true])]
    public function test_zero_and_unlimited_limits_are_enforced_on_acceptance(?int $total, ?int $explorer, ?int $mentor, string $type, bool $allowed): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $owner = User::factory()->create(['status' => 'active']);
        $plan = Plan::factory()->create(['max_projects' => $total, 'max_explorer_projects' => $explorer, 'max_mentor_projects' => $mentor]);
        UserMembership::create(['user_id' => $owner->id, 'plan_id' => $plan->id, 'source' => 'manual', 'terms' => $plan->terms(),
            'starts_at' => now()->subDay(), 'ends_at' => now()->addDay()]);
        $completed = $this->quotaOpportunity($admin);
        $owner->projects()->create(['opportunity_id' => $completed->id, 'title' => $completed->title,
            'source' => 'self', 'created_by' => $owner->id, 'participation_type' => 'explorer', 'status' => 'completed',
            'accepted_at' => now()->subDay(), 'started_at' => now()->subDay(), 'completed_at' => now(),
            'opportunity_version' => 1, 'offer_snapshot' => [], 'eligibility_snapshot' => []]);
        $offer = $this->quotaOpportunity($admin);
        $response = $this->actingAs($owner)->postJson('/opportunities/'.$offer->id.'/projects', ['participation_type' => $type]);
        if ($allowed) {
            $response->assertRedirect();
            $this->assertSame(1, $owner->projects()->where('status', 'accepted')->count());
        } else {
            $response->assertUnprocessable()->assertJsonValidationErrors('membership');
            $this->assertSame(0, $owner->projects()->where('status', 'accepted')->count());
        }
    }

    public function test_expired_codes_and_deactivated_plans_cannot_issue_entitlements(): void
    {
        $plan = Plan::factory()->create(['trial_days' => 5]);
        $admin = User::factory()->create(['status' => 'active', 'role' => 'super_admin']);
        $owner = User::factory()->create(['status' => 'active']);
        $issue = ['plan_id' => $plan->id, 'duration_days' => 7, 'expires_at' => now()->addDay(), 'request_key' => Str::uuid()];
        $code = $this->actingAs($admin)->postJson('/admin/subscriptions/codes', $issue)->assertCreated()->json('code');
        $redeem = ['code' => $code, 'timing' => 'now', 'request_key' => Str::uuid()];
        $this->patchJson('/admin/plans/'.$plan->id, ['is_active' => false, 'version' => 1, 'request_key' => Str::uuid()])->assertOk();
        $this->actingAs($owner)->postJson('/subscriptions/redeem', $redeem)->assertConflict();
        $this->postJson('/subscriptions/trial', ['plan_id' => $plan->id, 'request_key' => Str::uuid()])->assertConflict();
        $this->actingAs($admin)->patchJson('/admin/plans/'.$plan->id, ['is_active' => true, 'version' => 2, 'request_key' => Str::uuid()])->assertOk();
        $this->travel(2)->days();
        $this->actingAs($owner)->postJson('/subscriptions/redeem', $redeem)->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->assertDatabaseCount('user_memberships', 0);
        $this->assertDatabaseHas('subscription_promo_codes', ['code_hash' => hash('sha256', $code), 'redeemed_at' => null]);
    }

    public function test_enrollment_checks_the_owner_level_and_both_quotas_and_expiry_stops_mentor_referrals(): void
    {
        $owner = User::factory()->create(['status' => 'active']);
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $plan = Plan::where('seed_key', 'standard')->sole();
        $this->actingAs($owner)->postJson('/subscriptions/trial', ['plan_id' => $plan->id, 'request_key' => Str::uuid()])->assertCreated();
        $category = Category::create(['name' => 'Servicii', 'slug' => 'servicii', 'status' => 'active', 'visibility' => 'all']);
        $offer = Opportunity::create(['category_id' => $category->id, 'title' => 'Campanie de test', 'summary' => 'Descriere',
            'provider' => 'Furnizor', 'terms' => 'Regulament', 'difficulty' => 'Ușor', 'estimated_minutes' => 10, 'status' => 'published',
            'starts_at' => now()->subDay(), 'reward_label' => 'Beneficiu', 'reward_type' => 'non_cash', 'reward_unit' => 'non_cash',
            'payer' => 'Furnizor', 'created_by' => $admin->id, 'updated_by' => $admin->id, 'participation_mode' => 'both', 'min_access_level' => 2]);
        $url = '/opportunities/'.$offer->id.'/projects';
        $this->get('/opportunities/'.$offer->id)->assertNotFound();
        $this->postJson($url)->assertUnprocessable()->assertJsonValidationErrors('membership');
        $offer->update(['min_access_level' => 1]);
        $this->postJson($url, ['participation_type' => 'mentor'])->assertUnprocessable()->assertJsonValidationErrors('participation_type');

        $template = $offer->templates()->create(['title' => 'Confirmare', 'position' => 1, 'required_action' => 'Confirmă',
            'verification_method' => 'user_declaration', 'xp_points' => 0, 'assign_on_accept' => true, 'is_required' => true, 'status' => 'active']);
        $template->steps()->create(['title' => 'Pas', 'position' => 1, 'status' => 'active', 'required_action' => 'Confirmă', 'xp_points' => 0]);
        foreach (['completed', 'accepted', 'active'] as $status) {
            $existingOffer = $offer->replicate();
            $existingOffer->save();
            $project = $owner->projects()->create(['opportunity_id' => $existingOffer->id, 'title' => $existingOffer->title,
                'source' => 'self', 'created_by' => $owner->id, 'participation_type' => $status === 'completed' ? 'explorer' : 'mentor',
                'status' => $status, 'accepted_at' => now()->subDay(), 'started_at' => $status === 'accepted' ? null : now(),
                'completed_at' => $status === 'completed' ? now() : null, 'opportunity_version' => 1, 'offer_snapshot' => [], 'eligibility_snapshot' => []]);
        }
        $this->postJson($url, ['participation_type' => 'mentor'])->assertUnprocessable()->assertJsonValidationErrors('membership');
        $this->actingAs($admin)->postJson('/admin/projects', ['opportunity_id' => $offer->id, 'user_id' => $owner->id,
            'participation_type' => 'mentor'])->assertUnprocessable()->assertJsonValidationErrors('membership');
        $this->actingAs($owner)->post($url)->assertSessionHasNoErrors();
        $additional = $offer->replicate();
        $additional->save();
        $this->postJson('/opportunities/'.$additional->id.'/projects')->assertUnprocessable()->assertJsonValidationErrors('membership');
        $access = app(SubscriptionAccess::class);
        $this->assertTrue($access->canDistributeMentorReferral($owner, $project));
        $this->travel(30)->days();
        $this->assertFalse($access->canDistributeMentorReferral($owner, $project));
        $this->assertSame(4, Project::where('user_id', $owner->id)->count());
    }

    public function test_catalog_changes_are_authorized_versioned_and_preserve_existing_terms(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $plan = Plan::where('seed_key', 'standard')->sole();
        $this->actingAs($user)->postJson('/subscriptions/trial', ['plan_id' => $plan->id, 'request_key' => Str::uuid()])->assertCreated();
        $edit = ['name' => 'Standard Plus', 'max_projects' => 1, 'max_explorer_projects' => 1, 'max_mentor_projects' => 1, 'monthly_price_minor' => 2500, 'version' => 1, 'request_key' => Str::uuid()];
        $this->patchJson('/admin/plans/'.$plan->id, $edit)->assertForbidden();
        $this->actingAs($admin)->patchJson('/admin/plans/'.$plan->id, $edit)->assertOk()->assertJsonPath('version', 2);
        $this->patchJson('/admin/plans/'.$plan->id, array_merge($edit, ['request_key' => Str::uuid()]))->assertConflict();
        $this->seed(SubscriptionPlanSeeder::class);
        $this->assertDatabaseCount('plans', 3);
        $this->assertSame('Standard Plus', $plan->fresh()->name);
        $this->assertSame(3, UserMembership::sole()->terms['max_projects']);
        $this->assertSame(1900, UserMembership::sole()->terms['monthly_price_minor']);

        $custom = $plan->fresh()->terms();
        unset($custom['version']);
        $created = $this->postJson('/admin/plans', ['name' => 'Nou', 'is_active' => true, 'request_key' => Str::uuid()] + $custom)->assertCreated();
        $this->patchJson('/admin/plans/'.$created->json('id'), ['is_active' => false, 'version' => 1, 'request_key' => Str::uuid()])->assertOk();
        $this->actingAs($user)->getJson('/subscriptions/overview')->assertOk()->assertJsonCount(3, 'plans');
        $this->travel(31)->days();
        $this->postJson('/subscriptions/trial', ['plan_id' => $plan->id, 'request_key' => Str::uuid()])->assertUnprocessable();
        $this->getJson('/subscriptions/overview')->assertJsonPath('current', null)->assertJsonPath('trial_available', false);
        $this->artisan('memberships:expire')->assertSuccessful();
        $this->artisan('memberships:expire')->assertSuccessful();
        $this->assertSame(1, SubscriptionEvent::where('type', 'expired')->count());
    }

    public function test_reserved_code_is_single_use_and_only_switches_at_the_selected_boundary(): void
    {
        $owner = User::factory()->create(['status' => 'active']);
        $other = User::factory()->create(['status' => 'active']);
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $standard = Plan::where('seed_key', 'standard')->sole();
        $premium = Plan::where('seed_key', 'premium')->sole();
        $manual = ['plan_id' => $standard->id, 'duration_days' => 10, 'reason' => 'Contract', 'timing' => 'now', 'request_key' => Str::uuid()];
        $url = '/admin/users/'.$owner->id.'/memberships';
        $this->actingAs($other)->postJson($url, $manual)->assertForbidden();
        $this->actingAs($admin)->postJson($url, $manual)->assertCreated();
        $this->postJson($url, $manual)->assertConflict();
        $code = $this->postJson('/admin/subscriptions/codes', ['plan_id' => $premium->id, 'duration_days' => 20, 'reserved_user_id' => $owner->id,
            'expires_at' => now()->addDay(), 'request_key' => Str::uuid()])->assertCreated()->json('code');
        $redeem = ['code' => $code, 'timing' => 'period_end', 'request_key' => Str::uuid()];
        $this->actingAs($other)->postJson('/subscriptions/redeem', $redeem)->assertUnprocessable();
        $this->actingAs($owner)->postJson('/subscriptions/redeem', $redeem)->assertCreated();
        $this->postJson('/subscriptions/redeem', array_merge($redeem, ['request_key' => Str::uuid()]))->assertUnprocessable();
        $this->assertDatabaseCount('user_memberships', 2);
        $this->getJson('/subscriptions/overview')->assertJsonPath('current.plan_id', $standard->id);
        $change = UserMembership::where('plan_id', $premium->id)->sole();
        $this->travelTo($change->starts_at);
        $this->getJson('/subscriptions/overview')->assertJsonPath('current.plan_id', $premium->id);
        $this->actingAs($admin)->postJson($url, array_merge($manual, ['timing' => 'period_end', 'request_key' => Str::uuid()]))->assertCreated();
        $this->actingAs($owner)->getJson('/subscriptions/overview')->assertJsonPath('current.plan_id', $premium->id);
        $this->travelTo($change->ends_at);
        $this->getJson('/subscriptions/overview')->assertJsonPath('current.plan_id', $standard->id);
    }

    public function test_immediate_upgrade_and_extension_do_not_grant_duplicate_days(): void
    {
        $owner = User::factory()->create(['status' => 'active']);
        $admin = User::factory()->create(['status' => 'active', 'role' => 'super_admin']);
        $standard = Plan::where('seed_key', 'standard')->sole();
        $premium = Plan::where('seed_key', 'premium')->sole();
        $this->actingAs($owner)->postJson('/subscriptions/trial', ['plan_id' => $standard->id, 'request_key' => Str::uuid()])->assertCreated();
        $grant = ['plan_id' => $premium->id, 'duration_days' => 7, 'reason' => 'Acces acordat', 'timing' => 'now'];
        $url = '/admin/users/'.$owner->id.'/memberships';
        $trial = UserMembership::sole();
        $this->actingAs($admin)->postJson($url, $grant + ['request_key' => Str::uuid()])
            ->assertUnprocessable()->assertJsonValidationErrors('confirm_replace_membership_id');
        $this->assertNull($trial->fresh()->closed_at);
        $this->postJson($url, $grant + ['request_key' => Str::uuid(), 'confirm_replace_membership_id' => $trial->id])->assertCreated();
        $current = UserMembership::where('user_id', $owner->id)->effectiveAt(now())->sole();
        $this->assertSame($premium->id, $current->plan_id);
        $extension = $grant + ['request_key' => Str::uuid()];
        $next = $this->postJson($url, $extension)->assertCreated();
        $this->postJson($url, $extension)->assertConflict();
        $this->assertEquals($current->ends_at, UserMembership::findOrFail($next->json('id'))->starts_at);
        $business = Plan::where('seed_key', 'business')->sole();
        $this->postJson($url, array_merge($grant, ['plan_id' => $business->id, 'duration_days' => 2, 'request_key' => Str::uuid(),
            'confirm_replace_membership_id' => $current->id]))->assertCreated();
        $this->actingAs($owner)->getJson('/subscriptions/overview')->assertJsonPath('current.plan_id', $business->id);
        $reserved = UserMembership::findOrFail($next->json('id'));
        $this->assertEquals($reserved->starts_at->addDays(7), $reserved->ends_at);
        $this->travelTo($reserved->starts_at);
        $this->getJson('/subscriptions/overview')->assertJsonPath('current.plan_id', $premium->id);
    }
}
