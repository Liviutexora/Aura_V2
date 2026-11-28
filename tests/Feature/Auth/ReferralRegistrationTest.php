<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\VerifyEmailCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use LogicException;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class ReferralRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_valid_referral_survives_reload_validation_and_another_link_then_starts_a_second_generation(): void
    {
        Notification::fake();
        $first = User::factory()->create();
        $other = User::factory()->create();
        $this->keepSession($this->get('/register?ref=invalid')->assertOk());
        $this->get('/register?ref='.$first->referral_code)->assertOk()->assertSee('Ai deschis o invitație AURA');
        $this->get('/register')->assertOk();
        $this->get('/register?ref='.$other->referral_code)->assertOk();
        $this->post('/register', [])->assertSessionHasErrors('email');

        $response = $this->post('/register', $this->registration('alexandra@example.test') + [
            'sponsor_id' => $other->id, 'ref' => $other->referral_code, 'email_verified_at' => now(),
        ])->assertRedirectToRoute('aura.verify-email');
        $alexandra = User::where('email', 'alexandra@example.test')->firstOrFail();
        $this->assertAuthenticatedAs($alexandra);
        $this->assertEquals($first->id, $alexandra->sponsor_id);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/D', $alexandra->referral_code);
        $this->assertNotSame($first->referral_code, $alexandra->referral_code);
        $this->assertNull($alexandra->email_verified_at);
        $this->assertDatabaseHas('user_profiles', ['user_id' => $alexandra->id]);

        $this->keepSession($response);
        $code = Notification::sent($alexandra, VerifyEmailCode::class)->sole()->code;
        $this->keepSession($this->post('/verify-email', ['code' => $code])->assertSessionHasNoErrors());
        $this->get('/referrals')->assertOk()->assertSee('/register?ref='.$alexandra->referral_code)
            ->assertSee('Nu ai încă recomandări directe.');
        $this->keepSession($this->post('/logout'));
        $this->get('/register?ref='.$alexandra->referral_code)->assertOk();
        $this->post('/register', $this->registration('maria@example.test'))->assertRedirectToRoute('aura.verify-email');
        $this->assertDatabaseHas('users', ['email' => 'maria@example.test', 'sponsor_id' => $alexandra->id]);
        $this->assertEquals(1, $first->sponsoredUsers()->count());
    }

    #[TestWith(['?ref=bad'])]
    #[TestWith(['?ref%5B%5D=bad'])]
    #[TestWith(['?ref=0123456789abcdef0123456789abcdef'])]
    #[TestWith([''])]
    public function test_only_an_invitation_captured_on_the_registration_page_can_assign_a_sponsor(string $query): void
    {
        $other = User::factory()->create();
        $this->keepSession($this->get('/register'.$query)->assertOk());
        $this->post('/register?ref='.$other->referral_code, $this->registration('new@example.test') + [
            'sponsor_id' => $other->id, 'ref' => $other->referral_code, 'referral_code' => $other->referral_code,
        ])->assertRedirectToRoute('aura.verify-email');
        $this->assertDatabaseHas('users', ['email' => 'new@example.test', 'sponsor_id' => null, 'email_verified_at' => null]);
    }

    public function test_an_inactive_sponsor_is_ignored_and_a_sponsor_suspended_before_registration_is_revalidated(): void
    {
        $inactive = User::factory()->create(['status' => 'disabled']);
        $active = User::factory()->create();
        $this->keepSession($this->get('/register?ref='.$inactive->referral_code)->assertOk());
        $this->get('/register?ref='.$active->referral_code)->assertOk();
        $this->get('/register')->assertSessionHas('registration.sponsor_id', $active->id);
        $active->forceFill(['status' => 'suspended'])->save();
        $this->post('/register', $this->registration('new@example.test'))->assertRedirectToRoute('aura.verify-email');
        $this->assertDatabaseHas('users', ['email' => 'new@example.test', 'sponsor_id' => null]);
    }

    public function test_an_existing_user_cannot_register_via_their_own_link_or_update_their_sponsor(): void
    {
        $owner = User::factory()->create();
        $child = User::factory()->for($owner, 'sponsor')->create();
        $this->actingAs($owner)->get('/register?ref='.$owner->referral_code)->assertRedirect();
        $this->post('/register', $this->registration('unused@example.test'))->assertRedirect();
        $this->assertDatabaseCount('users', 2);
        $this->post('/settings/profile', [
            'name' => $owner->name, 'email' => $owner->email, 'sponsor_id' => $child->id,
        ])->assertSessionHasNoErrors();
        $this->assertNull($owner->refresh()->sponsor_id);

        $this->expectException(LogicException::class);
        $owner->sponsor()->associate($child)->save();
    }

    public function test_a_guest_cannot_register_the_sponsors_existing_email_and_registration_is_rate_limited(): void
    {
        $sponsor = User::factory()->create(['email' => 'owner@example.test']);
        $this->keepSession($this->get('/register?ref='.$sponsor->referral_code)->assertOk());
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->post('/register', $this->registration('OWNER@example.test'))->assertSessionHasErrors('email');
        }
        $this->post('/register', $this->registration('another@example.test'))->assertTooManyRequests();
        $this->assertDatabaseCount('users', 1);
        $this->assertNull($sponsor->fresh()->sponsor_id);
    }

    public function test_a_new_record_cannot_be_its_own_sponsor_even_with_an_explicit_key(): void
    {
        $this->expectException(LogicException::class);
        User::factory()->create(['id' => 42, 'sponsor_id' => 42]);
    }

    #[TestWith(['http://localhost:8000'])]
    #[TestWith(['https://aura.example.test'])]
    public function test_personal_links_use_the_current_origin_and_the_list_only_discloses_direct_referrals(string $origin): void
    {
        $owner = User::factory()->create();
        $child = User::factory()->for($owner, 'sponsor')->create([
            'name' => 'Alexandra Numeprivat', 'status' => 'suspended', 'created_at' => '2026-10-02',
        ]);
        $grandchild = User::factory()->for($child, 'sponsor')->create(['name' => 'Nepublic Nepot']);
        $private = User::factory()->for($owner, 'sponsor')->create(['name' => 'privat@example.test']);
        $this->actingAs($owner->fresh())->get($origin.'/referrals?user_id='.$child->id)->assertOk()
            ->assertSee($origin.'/register?ref='.$owner->referral_code)->assertSee('Alexandra')
            ->assertSee('Membru AURA')->assertSee('02.10.2026')->assertSee('Inactiv')
            ->assertSeeInOrder(['Recomandări directe', '2', 'Conturi active', '1', 'Conturi inactive', '1'])
            ->assertDontSee('Numeprivat')->assertDontSee('Nepublic')->assertDontSee($private->name)
            ->assertDontSee($child->email)->assertDontSee($grandchild->referral_code)->assertDontSee($child->referral_code)
            ->assertDontSee('Link demonstrativ')->assertDontSee('assets/aura/app.js');
    }

    public function test_referrals_require_an_active_account_and_sponsor_details_are_read_only_in_admin(): void
    {
        $this->get('/referrals')->assertRedirectToRoute('aura.login');
        $admin = User::factory()->create(['role' => 'super_admin']);
        $user = User::factory()->for($admin, 'sponsor')->create(['status' => 'disabled']);
        User::factory()->for($user, 'sponsor')->count(2)->create();
        $this->actingAs($user)->get('/referrals')->assertForbidden();
        $this->actingAs($admin->fresh())->get('/admin/users/'.$user->id)->assertOk()
            ->assertSee($user->referral_code)->assertSeeInOrder(['Sponsor', $admin->name, 'Recomandări directe', '2']);
        $this->get('/admin/referrals')->assertRedirectToRoute('aura.admin.users');
        $this->get('/admin/links/old/edit')->assertRedirectToRoute('aura.admin.users');
        $this->get('/admin/users')->assertOk()->assertSee('Sponsor direct')->assertSee($user->referral_code);
        $this->get('/admin/users/'.$user->id.'/edit')->assertOk()->assertDontSee('name="sponsor_id"', false);
    }

    public function test_data_migration_changes_only_legacy_codes_and_is_safe_to_repeat(): void
    {
        $sponsor = User::factory()->create(['referral_code' => 'LegacyName12345']);
        $child = User::factory()->for($sponsor, 'sponsor')->create();
        $migration = require database_path('migrations/2026_11_19_113247_replace_legacy_user_referral_codes.php');
        $migration->up();
        $replacement = $sponsor->refresh()->referral_code;
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/D', $replacement);
        $migration->up();
        $this->assertSame($replacement, $sponsor->fresh()->referral_code);
        $this->assertDatabaseHas('users', [
            'id' => $child->id, 'referral_code' => $child->referral_code, 'sponsor_id' => $sponsor->id,
        ]);
    }

    private function keepSession(TestResponse $response): void
    {
        $cookie = config('session.cookie');
        $this->withCookie($cookie, $response->getCookie($cookie)->getValue());
    }

    /** @return array<string, string> */
    private function registration(string $email): array
    {
        return [
            'name' => 'Persoană recomandată', 'email' => $email, 'terms' => '1',
            'phone' => '0712345678',
            'password' => 'Test-password-123', 'password_confirmation' => 'Test-password-123',
        ];
    }
}
