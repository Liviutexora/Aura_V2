<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\VerifyEmailCode;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class ProfilePhoneTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_settings_show_the_current_phone_or_an_empty_field(): void
    {
        $user = User::factory()->create(['status' => 'active', 'phone' => '+40712345678']);

        $this->actingAs($user)->get('/settings')->assertOk()
            ->assertSeeInOrder(['Nume complet', 'Email', 'Telefon', 'Limba interfeței'])
            ->assertSee('id="profile-phone" name="phone" type="tel" value="+40712345678"', false);

        $user->forceFill(['phone' => null])->save();
        $this->get('/settings')->assertOk()
            ->assertSee('id="profile-phone" name="phone" type="tel" value=""', false);
    }

    #[TestWith(['0712 345 678', '+40712345678'])]
    #[TestWith(['+44 (20) 1234-5678', '+442012345678'])]
    public function test_phone_is_normalized_and_saved_for_only_the_signed_in_user(string $submitted, string $expected): void
    {
        Notification::fake();
        $user = User::factory()->create(['status' => 'active', 'phone' => null]);
        $other = User::factory()->create(['status' => 'active', 'phone' => '+40799999999']);
        $verifiedAt = $user->email_verified_at?->toDateTimeString();

        $this->actingAs($user)->from('/settings')->post('/settings/profile', [
            'name' => 'Nume Actualizat',
            'email' => $user->email,
            'phone' => $submitted,
            'locale' => 'ro',
        ])->assertRedirect('/settings')->assertSessionHasNoErrors();

        $this->assertSame($expected, $user->fresh()->phone);
        $this->assertSame('Nume Actualizat', $user->fresh()->name);
        $this->assertSame($verifiedAt, $user->fresh()->email_verified_at?->toDateTimeString());
        $this->assertSame('ro', $user->profile()->sole()->locale);
        $this->assertSame('+40799999999', $other->fresh()->phone);
        Notification::assertNothingSent();
        $this->get('/settings')->assertOk()->assertSee('value="'.$expected.'" autocomplete="tel"', false);
    }

    public function test_phone_can_be_cleared_for_an_existing_account(): void
    {
        $user = User::factory()->create(['status' => 'active', 'phone' => '+40712345678']);

        $this->actingAs($user)->from('/settings')->post('/settings/profile', [
            'name' => $user->name, 'email' => $user->email, 'phone' => '', 'locale' => 'ro',
        ])->assertRedirect('/settings')->assertSessionHasNoErrors();

        $this->assertNull($user->fresh()->phone);
    }

    public function test_invalid_phone_is_rejected_without_changing_profile(): void
    {
        $user = User::factory()->create(['status' => 'active', 'phone' => '+40712345678']);

        $this->actingAs($user)->from('/settings')->post('/settings/profile', [
            'name' => 'Alt Nume', 'email' => $user->email, 'phone' => '07012', 'locale' => 'ro',
        ])->assertRedirect('/settings')->assertSessionHasErrorsIn('profileUpdate', 'phone');

        $this->assertSame('+40712345678', $user->fresh()->phone);
        $this->assertNotSame('Alt Nume', $user->fresh()->name);
        $this->withCookie(config('session.cookie'), session()->getId());
        $this->get('/settings')->assertOk()
            ->assertSee('value="07012" autocomplete="tel"', false)
            ->assertSee('Introdu un număr internațional cu + sau un număr românesc 07xxxxxxxx.');
    }

    public function test_email_change_still_verifies_email_and_saves_phone_and_locale_together(): void
    {
        Notification::fake();
        $user = User::factory()->create(['status' => 'active', 'phone' => null]);

        $this->actingAs($user)->post('/settings/profile', [
            'name' => 'Alt Nume',
            'email' => 'nou@example.test',
            'phone' => '0712345678',
            'locale' => 'ro',
            'current_password' => 'password',
        ])->assertRedirectToRoute('aura.verify-email');

        $this->assertSame('nou@example.test', $user->fresh()->email);
        $this->assertSame('+40712345678', $user->fresh()->phone);
        $this->assertSame('ro', $user->profile()->sole()->locale);
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
        Notification::assertSentTo($user, VerifyEmailCode::class);
    }

    public function test_unsupported_locale_does_not_change_the_phone(): void
    {
        $user = User::factory()->create(['status' => 'active', 'phone' => '+40712345678']);

        $this->actingAs($user)->from('/settings')->post('/settings/profile', [
            'name' => $user->name, 'email' => $user->email, 'phone' => '0799999999', 'locale' => 'en',
        ])->assertRedirect('/settings')->assertSessionHasErrorsIn('profileUpdate', 'locale');

        $this->assertSame('+40712345678', $user->fresh()->phone);
    }
}
