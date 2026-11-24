<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\SubscriptionEvent;
use App\Models\SubscriptionPromoCode;
use App\Models\User;
use App\Models\UserMembership;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminMembershipTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_can_cancel_a_scheduled_period_and_keep_the_active_period_and_audit_intact(): void
    {
        $this->freezeSecond();
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $owner = User::factory()->create(['status' => 'active']);
        $plan = Plan::factory()->create();
        $grant = ['plan_id' => $plan->id, 'duration_days' => 4, 'timing' => 'period_end', 'reason' => 'Contract'];
        $grantUrl = route('aura.admin.subscriptions.grant', $owner);
        $this->actingAs($admin)->postJson($grantUrl, $grant + ['request_key' => Str::uuid()])->assertCreated();
        $current = UserMembership::sole();
        $scheduledId = $this->postJson($grantUrl, $grant + ['request_key' => Str::uuid()])->assertCreated()->json('id');
        $scheduled = UserMembership::findOrFail($scheduledId);
        $cancelUrl = route('aura.admin.subscriptions.periods.cancel', $scheduled);
        $index = route('aura.admin.subscriptions');
        $this->get($index)->assertOk()->assertSee($cancelUrl)->assertSee('Confirmă anularea')
            ->assertDontSee(route('aura.admin.subscriptions.periods.cancel', $current));

        $payload = ['starts_at' => $scheduled->starts_at->timestamp, 'ends_at' => $scheduled->ends_at->timestamp, 'request_key' => (string) Str::uuid()];
        $this->patchJson($cancelUrl, $payload)->assertNoContent();
        $this->patchJson($cancelUrl, $payload)->assertConflict();
        $this->patchJson($cancelUrl, array_replace($payload, ['request_key' => Str::uuid()]))
            ->assertUnprocessable()->assertJsonValidationErrors('period');

        $this->assertDatabaseCount('user_memberships', 2);
        $this->assertSame($current->getAttributes(), $current->fresh()->getAttributes());
        $this->assertSame($scheduled->terms, $scheduled->fresh()->terms);
        $this->assertSame($scheduled->starts_at->timestamp, $scheduled->fresh()->starts_at->timestamp);
        $this->assertSame($scheduled->ends_at->timestamp, $scheduled->fresh()->ends_at->timestamp);
        $this->assertDatabaseHas('subscription_events', ['type' => 'cancelled', 'actor_id' => $admin->id, 'membership_id' => $scheduledId]);
        $this->assertSame(1, SubscriptionEvent::where('type', 'cancelled')->count());
        $this->get($index.'?status=scheduled')->assertOk()->assertSee('Niciun abonament găsit');
        $this->get($index.'?status=cancelled')->assertOk()->assertSee('Anulat')->assertDontSee($cancelUrl);
        $this->get($index.'?tab=history')->assertOk()->assertSee('Anulare perioadă programată')->assertSee('Perioada #'.$scheduledId);

        $code = $this->postJson(route('aura.admin.subscriptions.codes.store'), [
            'plan_id' => $plan->id, 'duration_days' => 5, 'expires_at' => now()->addDay(), 'request_key' => Str::uuid(),
        ])->assertCreated()->json('code');
        $this->actingAs($owner)->get('/subscriptions')->assertOk()->assertDontSee('Plan programat');
        $this->postJson('/subscriptions/redeem', ['code' => $code, 'plan_id' => $plan->id, 'timing' => 'now', 'request_key' => Str::uuid()])->assertCreated();
        $this->assertTrue(UserMembership::latest('id')->firstOrFail()->starts_at->equalTo($current->ends_at));
    }

    public function test_cancellation_rejects_unauthorized_stale_or_dependent_periods(): void
    {
        $this->freezeSecond();
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $owner = User::factory()->create(['status' => 'active']);
        $plan = Plan::factory()->create();
        $period = UserMembership::create(['user_id' => $owner->id, 'plan_id' => $plan->id,
            'terms' => $plan->terms(), 'source' => 'manual', 'starts_at' => now()->addDay(), 'ends_at' => now()->addDays(3)]);
        $next = UserMembership::create(['user_id' => $owner->id, 'plan_id' => $plan->id,
            'terms' => $plan->terms(), 'source' => 'manual', 'starts_at' => now()->addDays(3), 'ends_at' => now()->addDays(5)]);
        $url = route('aura.admin.subscriptions.periods.cancel', $period);
        $payload = ['starts_at' => $period->starts_at->timestamp, 'ends_at' => $period->ends_at->timestamp, 'request_key' => (string) Str::uuid()];
        $this->patchJson($url, $payload)->assertUnauthorized();
        $this->actingAs($owner)->patchJson($url, $payload)->assertForbidden();

        $this->actingAs($admin)->patchJson($url, array_replace($payload, ['ends_at' => $payload['ends_at'] + 60]))
            ->assertUnprocessable()->assertJsonPath('errors.period.0', 'Datele perioadei s-au schimbat. Verifică noua programare și confirmă din nou.');
        $this->patchJson($url, $payload)->assertUnprocessable()
            ->assertJsonPath('errors.period.0', 'Perioada #'.$next->id.' depinde de cea selectată. Anulează mai întâi perioada ulterioară.');
        $this->travelTo($period->starts_at);
        $this->patchJson($url, $payload)->assertUnprocessable()->assertJsonValidationErrors('period');
        $this->assertNull($period->fresh()->closed_at);
        $this->assertNull($next->fresh()->closed_at);
        $this->assertDatabaseCount('subscription_events', 0);
    }

    public function test_admin_grants_extends_and_reactivates_an_expired_membership(): void
    {
        $this->freezeSecond();
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $owner = User::factory()->create(['status' => 'active']);
        $plan = Plan::factory()->create()->refresh();
        $url = route('aura.admin.subscriptions.grant', $owner);
        $grant = ['plan_id' => $plan->id, 'duration_days' => 4, 'timing' => 'period_end', 'reason' => 'Contract anual',
            'membership_form' => 1, 'request_key' => (string) Str::uuid()];

        $this->actingAs($admin)->post($url, $grant)->assertRedirectToRoute('aura.admin.users.show', ['user' => $owner, 'tab' => 'subscription']);
        $period = UserMembership::sole();
        $this->assertTrue($period->starts_at->equalTo(now()));
        $this->assertDatabaseHas('subscription_events', ['actor_id' => $admin->id, 'membership_id' => $period->id, 'type' => 'activated', 'reason' => 'Contract anual']);
        $this->post($url, $grant)->assertConflict();
        $this->assertDatabaseCount('user_memberships', 1);
        $this->assertDatabaseCount('subscription_events', 1);

        $snapshot = $period->terms;
        $plan->update(['name' => 'Plan actualizat']);
        $grant['request_key'] = (string) Str::uuid();
        $this->post($url, $grant)->assertRedirect();
        $extension = UserMembership::whereKeyNot($period->id)->sole();
        $this->assertTrue($extension->starts_at->equalTo($period->ends_at));
        $this->assertTrue($extension->ends_at->equalTo(now()->addDays(8)));
        $this->assertSame($snapshot, $period->fresh()->terms);
        $this->assertSame('Plan actualizat', $extension->terms['name']);
        $this->assertDatabaseHas('subscription_events', ['type' => 'extended', 'membership_id' => $extension->id]);

        $this->travel(12)->days();
        $grant['request_key'] = (string) Str::uuid();
        $this->post($url, $grant)->assertRedirect();
        $this->assertTrue(UserMembership::effectiveAt(now())->sole()->starts_at->equalTo(now()));
    }

    public function test_confirmed_immediate_downgrade_rejects_stale_confirmation_and_preserves_queued_periods(): void
    {
        $this->freezeSecond();
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $user = User::factory()->create(['status' => 'active']);
        $premium = Plan::where('seed_key', 'premium')->sole();
        $standard = Plan::where('seed_key', 'standard')->sole();
        $url = route('aura.admin.subscriptions.grant', $user);
        $input = ['duration_days' => 5, 'reason' => 'Schimbare solicitată', 'timing' => 'period_end', 'plan_id' => $premium->id];
        $this->actingAs($admin)->postJson($url, $input + ['request_key' => Str::uuid()])->assertCreated();
        $original = UserMembership::sole();
        $this->postJson($url, $input + ['request_key' => Str::uuid()])->assertCreated();
        $queued = UserMembership::whereKeyNot($original->id)->sole();

        $input['timing'] = 'now';
        $input['plan_id'] = $standard->id;
        $this->travel(1)->day();
        $this->postJson($url, $input + ['request_key' => Str::uuid()])->assertUnprocessable()->assertJsonValidationErrors('confirm_replace_membership_id');
        $confirmed = $input + ['request_key' => (string) Str::uuid(), 'confirm_replace_membership_id' => $original->id];
        $replacementId = $this->postJson($url, $confirmed)->assertCreated()->json('id');
        $this->postJson($url, $confirmed)->assertConflict();
        $confirmed['request_key'] = (string) Str::uuid();
        $this->postJson($url, $confirmed)->assertUnprocessable()->assertJsonValidationErrors('confirm_replace_membership_id');
        $this->assertDatabaseCount('user_memberships', 3);
        $this->assertTrue($original->fresh()->ends_at->equalTo(now()));
        $this->assertSame($replacementId, UserMembership::effectiveAt(now())->sole()->id);
        $queued->refresh();
        $this->assertTrue($queued->starts_at->equalTo(now()->addDays(5)));
        $this->assertTrue($queued->ends_at->equalTo(now()->addDays(10)));
        $this->assertDatabaseHas('subscription_events', ['type' => 'plan_changed', 'membership_id' => $replacementId, 'actor_id' => $admin->id]);
    }

    public function test_admin_list_filters_and_pages_real_periods_and_scopes_the_profile_history(): void
    {
        $this->freezeSecond();
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $owner = User::factory()->create(['status' => 'suspended']);
        $other = User::factory()->create(['status' => 'active']);
        $plan = Plan::factory()->create(['name' => 'Plan istoric']);
        $grant = ['timing' => 'period_end', 'plan_id' => $plan->id, 'duration_days' => 1, 'reason' => 'Acces manual'];
        $this->actingAs($admin);
        for ($i = 0; $i < 7; $i++) {
            $this->postJson(route('aura.admin.subscriptions.grant', $owner), $grant + ['request_key' => Str::uuid()])->assertCreated();
        }
        $this->postJson(route('aura.admin.subscriptions.grant', $other), array_replace($grant, [
            'reason' => 'Motiv distinct pentru alt cont', 'request_key' => Str::uuid(),
        ]))->assertCreated();
        $plan->update(['name' => 'Alt nume în catalog']);
        $this->travel(1)->day();

        $this->get('/admin/subscriptions?page=2&sort=user&direction=asc')->assertOk()->assertSee('Pagina 2 / 2');
        foreach (['active' => 1, 'expired' => 1, 'scheduled' => 5] as $status => $count) {
            $this->get(route('aura.admin.subscriptions', ['q' => $owner->email, 'status' => $status, 'sort' => 'status']))
                ->assertOk()->assertSee($owner->email)->assertSee('Plan istoric')->assertDontSee($other->email)
                ->assertViewHas('periods', fn ($periods) => $periods->total() === $count);
        }
        $this->get('/admin/subscriptions?q=istoric&sort=plan')->assertOk()->assertSee('Plan istoric')->assertDontSee('Alt nume în catalog');
        $this->get('/admin/subscriptions?range=7&status=scheduled')->assertOk()->assertSee('Niciun abonament găsit');
        $profile = route('aura.admin.users.show', ['user' => $owner, 'tab' => 'subscription']);
        $this->get($profile)->assertOk()->assertSee('Suspendat')->assertSee('Plan istoric')->assertSee('Prelungire')
            ->assertSee($admin->email)->assertSee('Acces manual')->assertDontSee('Motiv distinct pentru alt cont');
        $this->artisan('memberships:expire')->assertSuccessful();
        $this->get('/admin/subscriptions?tab=history')->assertOk()->assertSee('Expirare')->assertSee('Efectuat de sistem')
            ->assertSee($other->email)->assertSee('Motiv distinct pentru alt cont');
    }

    public function test_subscription_admin_routes_require_super_admin_and_validate_grants(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $url = route('aura.admin.subscriptions.grant', $user);
        $this->get('/admin/subscriptions')->assertRedirectToRoute('aura.admin.login');
        $this->actingAs($user);
        foreach (['subscriptions', 'subscriptions?tab=history', 'subscriptions/codes', 'subscriptions/history', 'promo-codes', 'users/'.$user->id.'?tab=subscription'] as $path) {
            $this->get('/admin/'.$path)->assertForbidden();
        }
        $this->postJson($url)->assertForbidden();
        $this->postJson('/admin/subscriptions/codes')->assertForbidden();

        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $this->actingAs($admin)->postJson($url, ['duration_days' => 0, 'timing' => 'invalid'])
            ->assertUnprocessable()->assertJsonValidationErrors(['plan_id', 'duration_days', 'timing', 'reason', 'request_key']);
        $inactive = Plan::factory()->create(['is_active' => false]);
        $this->postJson($url, ['plan_id' => $inactive->id, 'duration_days' => 3, 'timing' => 'now',
            'reason' => 'Contract', 'request_key' => Str::uuid()])->assertConflict();
        $this->assertDatabaseCount('user_memberships', 0);
        $this->assertDatabaseCount('subscription_events', 0);
    }

    public function test_subscription_writes_require_csrf_protection(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $owner = User::factory()->create(['status' => 'active']);
        $payload = ['plan_id' => Plan::factory()->create()->id, 'duration_days' => 3, 'timing' => 'period_end',
            'reason' => 'Contract', 'request_key' => (string) Str::uuid()];
        $this->actingAs($admin);
        $originalEnvironment = $this->app['env'];
        $this->app['env'] = 'production';
        try {
            $this->post(route('aura.admin.subscriptions.grant', $owner), $payload)->assertStatus(419);
            $this->post(route('aura.admin.subscriptions.codes.store'), $payload)->assertStatus(419);
            $this->delete(route('aura.admin.subscriptions.codes.destroy', 1), $payload)->assertStatus(419);
            $this->patch(route('aura.admin.subscriptions.periods.cancel', 1), $payload)->assertStatus(419);
        } finally {
            $this->app['env'] = $originalEnvironment;
        }
        $this->assertDatabaseCount('user_memberships', 0);
        $this->assertDatabaseCount('subscription_promo_codes', 0);
    }

    public function test_promo_admin_inventory_and_history_show_usage_without_disclosing_the_code_or_hash(): void
    {
        $this->freezeSecond();
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $owner = User::factory()->create(['status' => 'active']);
        $plan = Plan::factory()->create();
        $issue = ['plan_id' => $plan->id, 'duration_days' => 5, 'reserved_user_id' => $owner->id, 'expires_at' => now()->addDay(),
            'request_key' => (string) Str::uuid()];
        $url = '/admin/subscriptions/codes';
        $this->actingAs($admin);
        $code = $this->postJson($url, $issue)->assertCreated()->json('code');
        $this->postJson($url, $issue)->assertConflict();
        $this->getJson($url)->assertOk()->assertJsonPath('data.0.status', 'disponibil')->assertDontSee($code)
            ->assertDontSee(hash('sha256', strtolower($code)));
        $this->assertDatabaseHas('subscription_promo_codes', ['code_hash' => hash('sha256', strtolower($code))]);

        $this->actingAs($owner)->postJson('/subscriptions/redeem', ['plan_id' => $plan->id, 'code' => $code, 'timing' => 'now', 'request_key' => Str::uuid()])->assertCreated();
        $this->actingAs($admin)->postJson($url, array_replace($issue, ['request_key' => Str::uuid()]))->assertCreated();
        $this->travel(1)->day();
        $this->getJson($url)->assertJsonPath('data.0.status', 'expirat')->assertJsonPath('data.1.status', 'utilizat')
            ->assertJsonPath('data.1.membership.user.email', $owner->email)->assertDontSee($code);
        $this->get('/admin/subscriptions?tab=history')->assertOk()->assertSee('Generare cod promoțional')
            ->assertSee('Promoție')->assertSee($owner->email)->assertDontSee($code)->assertDontSee(hash('sha256', strtolower($code)));
    }

    public function test_the_change_plan_dialog_targets_the_selected_users_current_period_and_posts_to_the_grant_endpoint(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $owner = User::factory()->create(['status' => 'active']);
        $standard = Plan::where('seed_key', 'standard')->sole();
        $premium = Plan::where('seed_key', 'premium')->sole();
        Plan::factory()->create(['name' => 'Plan retras', 'is_active' => false]);
        $open = route('aura.admin.subscriptions', ['manage_user' => $owner->id]);
        $action = route('aura.admin.subscriptions.grant', $owner);
        $this->actingAs($admin)->get(route('aura.admin.users.show', $owner))->assertOk()->assertSee($open);
        $this->get($open)->assertOk()->assertSee('name="duration_days"', false)->assertDontSee('name="confirm_replace_membership_id"', false);

        $payload = ['plan_id' => $standard->id, 'timing' => 'period_end', 'duration_days' => 4, 'reason' => 'Acordare'];
        $current = $this->postJson($action, $payload + ['request_key' => Str::uuid()])->assertCreated()->json('id');
        $this->postJson($action, $payload + ['request_key' => Str::uuid()])->assertCreated();
        $this->get('/admin/subscriptions?status=scheduled')->assertOk()->assertSee('Schimbă planul pentru '.$owner->name)
            ->assertSee('manage_user='.$owner->id);
        $modal = $this->get($open)->assertOk()->assertSee($action)->assertSee($owner->email)->assertSee('name="_token"', false)
            ->assertSee('value="period_end"', false)->assertSee('value="now"', false)->assertSee('name="reason"', false)
            ->assertSee('name="confirm_replace_membership_id" value="'.$current.'"', false)->assertSee($premium->name)->assertDontSee('Plan retras');
        preg_match('/name="request_key" value="([^"]+)"/', $modal->getContent(), $key);
        $this->assertTrue(Str::isUuid($key[1] ?? ''));
        $change = ['request_key' => $key[1], 'plan_id' => $premium->id, 'timing' => 'now', 'duration_days' => 12, 'reason' => 'Cerere beneficiar'];
        $this->postJson($action, $change)->assertUnprocessable()->assertJsonValidationErrors('confirm_replace_membership_id');
        $change['confirm_replace_membership_id'] = $current;
        $this->postJson($action, $change)->assertCreated()->assertJsonPath('plan_id', $premium->id);
        $this->postJson($action, $change)->assertConflict();
        $this->assertSame($premium->id, UserMembership::effectiveAt(now())->sole()->plan_id);
    }

    public function test_promo_page_generates_unique_encrypted_batches_and_keeps_codes_visible(): void
    {
        $this->freezeSecond();
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $plan = Plan::factory()->create(['name' => 'Plan promo eligibil']);
        Plan::factory()->create(['name' => 'Ofertă retrasă', 'is_active' => false]);
        $index = route('aura.admin.promo-codes');
        $action = route('aura.admin.subscriptions.codes.store');
        $page = $this->actingAs($admin)->get($index)->assertOk()->assertSeeInOrder(['Abonamente utilizatori', 'Coduri promoționale', 'Activitate utilizatori'])
            ->assertSee('Generează cupoane')->assertSee('Copiază toate')->assertSee($action)->assertSee('name="_token"', false)
            ->assertSee('name="quantity"', false)->assertSee('name="expires_at"', false)
            ->assertSee($plan->name)->assertDontSee('Ofertă retrasă');
        preg_match('/name="request_key" value="([^"]+)"/', $page->getContent(), $key);
        $this->assertTrue(Str::isUuid($key[1] ?? ''));
        $expiry = now()->addHours(2);
        $payload = ['plan_id' => $plan->id, 'duration_days' => 12, 'quantity' => 3,
            'expires_at' => $expiry->format('Y-m-d\TH:i'), 'reason' => 'Invitație partener', 'request_key' => $key[1]];
        $this->postJson($action, array_replace($payload, ['quantity' => 101, 'expires_at' => now()->subDay(), 'reason' => str_repeat('x', 1001)]))
            ->assertUnprocessable()->assertJsonValidationErrors(['quantity', 'reason', 'expires_at']);
        $receipt = $this->postJson($action, $payload)->assertCreated();
        $this->assertStringContainsString('no-store', $receipt->headers->get('Cache-Control'));
        $codes = collect($receipt->json('codes'))->pluck('code');
        $this->assertCount(3, $codes);
        $this->assertCount(3, $codes->unique());
        foreach ($codes as $code) {
            $this->assertMatchesRegularExpression('/\A[0-9A-Z]{9}\z/', $code);
            $this->assertDatabaseHas('subscription_promo_codes', [
                'code_hash' => hash('sha256', strtolower($code)),
                'created_by' => $admin->id,
                'duration_days' => 12,
                'expires_at' => $expiry->startOfMinute()->toDateTimeString(),
            ]);
        }
        $rawCodes = DB::table('subscription_promo_codes')->pluck('encrypted_code');
        $this->assertTrue($rawCodes->every(fn (string $encrypted): bool => ! $codes->contains($encrypted)));
        $this->assertSame($codes->sort()->values()->all(), SubscriptionPromoCode::all()->pluck('encrypted_code')->sort()->values()->all());
        $this->assertDatabaseCount('subscription_events', 3);
        $this->postJson($action, $payload)->assertConflict();
        $this->assertDatabaseCount('subscription_promo_codes', 3);

        $inventory = $this->get($index.'?'.http_build_query(['q' => $plan->name, 'status' => 'disponibil']))->assertOk();
        foreach ($codes as $code) {
            $inventory->assertSee($code)->assertDontSee(hash('sha256', strtolower($code)));
        }
    }

    public function test_only_super_admin_can_revoke_unused_codes_and_used_codes_cannot_be_revoked(): void
    {
        $this->freezeSecond();
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $owner = User::factory()->create(['status' => 'active']);
        $plan = Plan::factory()->create();
        $issue = ['plan_id' => $plan->id, 'duration_days' => 5, 'quantity' => 1, 'expires_at' => now()->addDay(),
            'request_key' => (string) Str::uuid()];
        $firstCode = $this->actingAs($admin)->postJson(route('aura.admin.subscriptions.codes.store'), $issue)
            ->assertCreated()->json('codes.0');
        $revokeUrl = route('aura.admin.subscriptions.codes.revoke', $firstCode['id']);
        $revokeKey = (string) Str::uuid();

        $this->actingAs($owner)->patchJson($revokeUrl, ['request_key' => $revokeKey])->assertForbidden();
        $this->actingAs($admin)->patchJson($revokeUrl, ['request_key' => $revokeKey])
            ->assertOk()->assertJsonPath('status', 'revocat');
        $this->assertDatabaseHas('subscription_promo_codes', ['id' => $firstCode['id'], 'revoked_by' => $admin->id, 'revoked_at' => now()->toDateTimeString()]);
        $this->assertDatabaseHas('subscription_events', ['type' => 'promo_revoked', 'promo_code_id' => $firstCode['id'], 'actor_id' => $admin->id]);
        $this->patchJson($revokeUrl, ['request_key' => $revokeKey])->assertConflict();
        $this->actingAs($owner)->postJson('/subscriptions/redeem', ['plan_id' => $plan->id, 'code' => $firstCode['code'], 'timing' => 'now',
            'request_key' => Str::uuid()])->assertUnprocessable()->assertJsonValidationErrors('code');

        $secondCode = $this->actingAs($admin)->postJson(route('aura.admin.subscriptions.codes.store'), array_replace($issue, [
            'request_key' => (string) Str::uuid(),
        ]))->assertCreated()->json('codes.0');
        $this->actingAs($owner)->postJson('/subscriptions/redeem', ['plan_id' => $plan->id, 'code' => $secondCode['code'], 'timing' => 'now',
            'request_key' => Str::uuid()])->assertCreated();
        $this->actingAs($admin)->patchJson(route('aura.admin.subscriptions.codes.revoke', $secondCode['id']), [
            'request_key' => (string) Str::uuid(),
        ])->assertUnprocessable()->assertJsonValidationErrors('promo_code');
        $this->assertDatabaseHas('subscription_promo_codes', ['id' => $secondCode['id'], 'revoked_at' => null]);
        $this->assertDatabaseCount('user_memberships', 1);
    }

    public function test_legacy_codes_without_encrypted_values_remain_redeemable_but_cannot_be_displayed(): void
    {
        $this->freezeSecond();
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $owner = User::factory()->create(['status' => 'active']);
        $plan = Plan::factory()->create();
        $legacyCode = str_repeat('a', 40);
        SubscriptionPromoCode::create([
            'code_hash' => hash('sha256', $legacyCode),
            'plan_id' => $plan->id,
            'duration_days' => 7,
            'expires_at' => now()->addDay(),
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->get(route('aura.admin.promo-codes'))->assertOk()
            ->assertSee('Indisponibil (cod vechi)')->assertDontSee($legacyCode);
        $this->actingAs($owner)->postJson('/subscriptions/redeem', ['plan_id' => $plan->id,
            'code' => $legacyCode,
            'timing' => 'now',
            'request_key' => Str::uuid(),
        ])->assertCreated();
        $this->assertDatabaseCount('user_memberships', 1);
    }

    public function test_unreserved_codes_can_be_found_by_the_eventual_recipient_and_grant_the_full_duration(): void
    {
        $this->freezeSecond();
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $owner = User::factory()->create(['status' => 'active']);
        $plan = Plan::factory()->create();
        $receipt = $this->actingAs($admin)->postJson(route('aura.admin.subscriptions.codes.store'), [
            'plan_id' => $plan->id, 'duration_days' => 14, 'reserved_user_id' => '', 'expires_at' => now()->addDays(60), 'reason' => '',
            'request_key' => Str::uuid(),
        ])->assertCreated();
        $index = route('aura.admin.promo-codes');
        $this->get($index.'?status=disponibil')->assertOk()->assertSee($receipt->json('code'))
            ->assertViewHas('codes', fn ($codes) => $codes->total() === 1 && $codes->first()->reserved_user_id === null);
        $this->travel(30)->days();
        $this->actingAs($owner)->postJson('/subscriptions/redeem', ['plan_id' => $plan->id, 'code' => $receipt->json('code'),
            'timing' => 'now', 'request_key' => Str::uuid()])->assertCreated();
        $this->assertTrue(UserMembership::sole()->ends_at->equalTo(now()->addDays(14)));
        $this->actingAs($admin)->get($index.'?'.http_build_query(['q' => $owner->email, 'status' => 'utilizat']))
            ->assertOk()->assertViewHas('codes', fn ($codes) => $codes->total() === 1 && $codes->first()->membership->user->is($owner))
            ->assertSee($receipt->json('code'));
    }

    public function test_promo_tabs_separate_available_and_archived_codes_and_only_unused_codes_can_be_deleted(): void
    {
        $this->freezeSecond();
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $owner = User::factory()->create(['status' => 'active']);
        $plan = Plan::factory()->create();
        $index = route('aura.admin.promo-codes');
        [$available, $revoked, $expired, $used] = $this->actingAs($admin)
            ->postJson(route('aura.admin.subscriptions.codes.store'), [
                'plan_id' => $plan->id, 'duration_days' => 5, 'quantity' => 4,
                'expires_at' => now()->addDay(), 'request_key' => (string) Str::uuid(),
            ])->assertCreated()->json('codes');
        $this->patchJson(route('aura.admin.subscriptions.codes.revoke', $revoked['id']), [
            'request_key' => (string) Str::uuid(),
        ])->assertOk();
        SubscriptionPromoCode::findOrFail($expired['id'])->update(['expires_at' => now()]);
        $this->actingAs($owner)->postJson('/subscriptions/redeem', ['plan_id' => $plan->id,
            'code' => $used['code'], 'timing' => 'now', 'request_key' => (string) Str::uuid(),
        ])->assertCreated();

        $this->actingAs($admin)->get($index)->assertOk()->assertSee($available['code'])
            ->assertDontSee($revoked['code'])->assertDontSee($expired['code'])->assertDontSee($used['code']);
        $this->get($index.'?tab=arhiva')->assertOk()->assertDontSee($available['code'])
            ->assertSee($revoked['code'])->assertSee($expired['code'])->assertSee($used['code'])->assertSee($owner->email);
        $this->get($index.'?'.http_build_query(['tab' => 'arhiva', 'status' => 'utilizat', 'q' => $owner->email]))
            ->assertOk()->assertViewHas('codes', fn ($codes) => $codes->pluck('id')->all() === [$used['id']]);

        $payload = ['request_key' => (string) Str::uuid(), 'confirm_delete' => 1];
        $availableUrl = route('aura.admin.subscriptions.codes.destroy', $available['id']);
        $this->actingAs($owner)->deleteJson($availableUrl, $payload)->assertForbidden();
        $this->actingAs($admin)->deleteJson($availableUrl, ['request_key' => $payload['request_key']])
            ->assertUnprocessable()->assertJsonValidationErrors('confirm_delete');
        $this->deleteJson(route('aura.admin.subscriptions.codes.destroy', $used['id']), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('promo_code');
        $this->assertDatabaseHas('subscription_promo_codes', ['id' => $used['id'], 'membership_id' => UserMembership::sole()->id]);

        foreach ([$available, $revoked, $expired] as $coupon) {
            $url = route('aura.admin.subscriptions.codes.destroy', $coupon['id']);
            $payload['request_key'] = (string) Str::uuid();
            $this->deleteJson($url, $payload)->assertNoContent();
            $this->deleteJson($url, $payload)->assertConflict();
            $this->assertDatabaseMissing('subscription_promo_codes', ['id' => $coupon['id']]);
            $events = SubscriptionEvent::where('details->deleted_promo_code_id', $coupon['id'])->get();
            $this->assertContains('promo_issued', $events->pluck('type'));
            $this->assertContains('promo_deleted', $events->pluck('type'));
            $this->assertTrue($events->every(fn (SubscriptionEvent $event): bool => $event->promo_code_id === null));
        }
        $this->assertDatabaseCount('subscription_events', 9);
    }

    public function test_promo_deletion_refuses_an_unexpected_audit_reference_and_rolls_back(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $plan = Plan::factory()->create();
        $id = $this->actingAs($admin)->postJson(route('aura.admin.subscriptions.codes.store'), [
            'plan_id' => $plan->id, 'duration_days' => 5,
            'expires_at' => now()->addDay(), 'request_key' => (string) Str::uuid(),
        ])->assertCreated()->json('id');
        $promo = SubscriptionPromoCode::findOrFail($id);
        SubscriptionEvent::record('unexpected_reference', $plan->id, $admin, promo: $promo);

        $this->deleteJson(route('aura.admin.subscriptions.codes.destroy', $promo), [
            'request_key' => (string) Str::uuid(), 'confirm_delete' => 1,
        ])->assertUnprocessable()->assertJsonValidationErrors('promo_code');

        $this->assertModelExists($promo);
        $this->assertDatabaseHas('subscription_events', ['type' => 'promo_issued', 'promo_code_id' => $promo->id]);
        $this->assertDatabaseCount('subscription_events', 2);
    }

    public function test_user_edit_allows_a_super_admin_to_allocate_the_first_membership(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $owner = User::factory()->create(['status' => 'active']);
        $activePlan = Plan::factory()->create(['name' => 'Plan pentru alocare']);
        Plan::factory()->create(['name' => 'Plan inactiv ascuns', 'is_active' => false]);
        $editUrl = route('aura.admin.users.edit', $owner);
        $grantUrl = route('aura.admin.subscriptions.grant', $owner);

        $page = $this->actingAs($admin)->get($editUrl)->assertOk()
            ->assertSee('value="Niciun abonament"', false)
            ->assertSee('Alocă abonament')
            ->assertSee($grantUrl)
            ->assertSee('name="_token"', false)
            ->assertSee('name="request_key"', false)
            ->assertSee($activePlan->name)
            ->assertDontSee('Plan inactiv ascuns');
        preg_match('/name="request_key" value="([^"]+)"/', $page->getContent(), $requestKey);
        $payload = ['plan_id' => $activePlan->id, 'duration_days' => 30, 'timing' => 'period_end',
            'reason' => 'Alocare din profilul utilizatorului', 'request_key' => $requestKey[1]];

        $this->postJson($grantUrl, $payload)->assertCreated();
        $this->postJson($grantUrl, $payload)->assertConflict();

        $membership = UserMembership::sole();
        $this->assertSame($owner->id, $membership->user_id);
        $this->assertSame($activePlan->id, $membership->plan_id);
        $this->assertDatabaseHas('subscription_events', ['type' => 'activated', 'actor_id' => $admin->id,
            'membership_id' => $membership->id, 'reason' => 'Alocare din profilul utilizatorului']);
        $this->get($editUrl)->assertOk()
            ->assertSee('value="Plan pentru alocare"', false)
            ->assertDontSee('Alocă abonament')
            ->assertSee(route('aura.admin.subscriptions', ['q' => $owner->email, 'status' => 'active']));
    }
}
