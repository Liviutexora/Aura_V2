<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileUpdateSecurityTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const CURRENT_PASSWORD = 'current-password-123';

    private const VERIFIED_AT = '2026-10-01 10:30:00';

    public function test_name_can_be_changed_without_current_password(): void
    {
        $user = $this->createUser(
            email: 'name-change@example.test',
            referralCode: 'NAMECHANGE',
        );

        $response = $this->actingAs($user)
            ->from(route('aura.settings'))
            ->post(route('aura.settings.profile.update'), [
                'name' => 'Updated Name',
                'email' => $user->email,
            ]);

        $response->assertRedirect(route('aura.settings'));
        $response->assertSessionHas('profile_status', 'Datele contului au fost actualizate.');
        $this->assertSame('Updated Name', $user->fresh()->name);
        $this->assertSame('name-change@example.test', $user->fresh()->email);
    }

    public function test_email_change_without_current_password_is_rejected(): void
    {
        $user = $this->createUser(
            email: 'missing-password@example.test',
            referralCode: 'MISSINGPASSWORD',
        );

        $response = $this->actingAs($user)
            ->from(route('aura.settings'))
            ->post(route('aura.settings.profile.update'), [
                'name' => $user->name,
                'email' => 'new-missing-password@example.test',
            ]);

        $response->assertRedirect(route('aura.settings'));
        $response->assertSessionHasErrorsIn('profileUpdate', [
            'current_password' => 'Introdu parola curentă pentru a schimba adresa de email.',
        ]);
        $this->assertSame('missing-password@example.test', $user->fresh()->email);
        $this->assertSame(self::VERIFIED_AT, $user->fresh()->email_verified_at?->format('Y-m-d H:i:s'));
    }

    public function test_email_change_with_incorrect_current_password_is_rejected(): void
    {
        $user = $this->createUser(
            email: 'wrong-password@example.test',
            referralCode: 'WRONGPASSWORD',
        );

        $response = $this->actingAs($user)
            ->from(route('aura.settings'))
            ->post(route('aura.settings.profile.update'), [
                'name' => $user->name,
                'email' => 'new-wrong-password@example.test',
                'current_password' => 'incorrect-password',
            ]);

        $response->assertRedirect(route('aura.settings'));
        $response->assertSessionHasErrorsIn('profileUpdate', [
            'current_password' => 'Parola curentă este incorectă.',
        ]);
        $response->assertSessionHas(
            '_old_input',
            fn (array $input): bool => ! array_key_exists('current_password', $input),
        );
        $this->assertSame('wrong-password@example.test', $user->fresh()->email);
        $this->assertSame(self::VERIFIED_AT, $user->fresh()->email_verified_at?->format('Y-m-d H:i:s'));
    }

    public function test_email_change_with_correct_current_password_is_allowed(): void
    {
        $user = $this->createUser(
            email: 'correct-password@example.test',
            referralCode: 'CORRECTPASSWORD',
            attributes: ['email_verified_at' => null],
        );

        $response = $this->actingAs($user)
            ->from(route('aura.settings'))
            ->post(route('aura.settings.profile.update'), [
                'name' => $user->name,
                'email' => 'NEW-CORRECT-PASSWORD@example.test',
                'current_password' => self::CURRENT_PASSWORD,
            ]);

        $response->assertRedirect(route('aura.settings'));
        $response->assertSessionHas(
            'profile_status',
            'Datele contului au fost actualizate. Noua adresă de email este neverificată.',
        );
        $this->assertSame('new-correct-password@example.test', $user->fresh()->email);
        $this->assertAuthenticatedAs($user);
        $this->assertTrue(Hash::check(self::CURRENT_PASSWORD, $user->fresh()->getAuthPassword()));
    }

    public function test_email_change_clears_email_verification_timestamp(): void
    {
        $user = $this->createUser(
            email: 'verified-email@example.test',
            referralCode: 'VERIFIEDEMAIL',
        );

        $response = $this->actingAs($user)
            ->from(route('aura.settings'))
            ->post(route('aura.settings.profile.update'), [
                'name' => $user->name,
                'email' => 'unverified-email@example.test',
                'current_password' => self::CURRENT_PASSWORD,
            ]);

        $response->assertRedirect(route('aura.settings'));
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_name_only_change_preserves_email_verification_timestamp(): void
    {
        $user = $this->createUser(
            email: 'verified-name-change@example.test',
            referralCode: 'VERIFIEDNAME',
        );

        $response = $this->actingAs($user)
            ->from(route('aura.settings'))
            ->post(route('aura.settings.profile.update'), [
                'name' => 'Verified User Updated',
                'email' => $user->email,
            ]);

        $response->assertRedirect(route('aura.settings'));
        $this->assertSame(self::VERIFIED_AT, $user->fresh()->email_verified_at?->format('Y-m-d H:i:s'));
    }

    public function test_profile_payload_cannot_modify_another_user(): void
    {
        $user = $this->createUser(
            email: 'profile-owner@example.test',
            referralCode: 'PROFILEOWNER',
        );
        $otherUser = $this->createUser(
            email: 'other-user@example.test',
            referralCode: 'OTHERUSER',
            attributes: ['name' => 'Other User'],
        );

        $response = $this->actingAs($user)
            ->from(route('aura.settings'))
            ->post(route('aura.settings.profile.update'), [
                'user_id' => $otherUser->getKey(),
                'name' => 'Profile Owner Updated',
                'email' => $user->email,
            ]);

        $response->assertRedirect(route('aura.settings'));
        $this->assertSame('Profile Owner Updated', $user->fresh()->name);
        $this->assertSame('Other User', $otherUser->fresh()->name);
        $this->assertSame('other-user@example.test', $otherUser->fresh()->email);
    }

    public function test_profile_payload_cannot_modify_sensitive_user_fields(): void
    {
        $user = $this->createUser(
            email: 'sensitive-fields@example.test',
            referralCode: 'SENSITIVEFIELDS',
        );
        $originalId = $user->getKey();
        $originalPasswordHash = $user->getAuthPassword();

        $response = $this->actingAs($user)
            ->from(route('aura.settings'))
            ->post(route('aura.settings.profile.update'), [
                'id' => 999999,
                'role' => 'super_admin',
                'status' => 'disabled',
                'password' => 'attacker-controlled-password',
                'email_verified_at' => null,
                'name' => 'Safe Profile Update',
                'email' => $user->email,
            ]);

        $response->assertRedirect(route('aura.settings'));

        $persistedUser = User::findOrFail($originalId);

        $this->assertSame($originalId, $persistedUser->getKey());
        $this->assertSame('user', $persistedUser->role);
        $this->assertSame('active', $persistedUser->status);
        $this->assertSame($originalPasswordHash, $persistedUser->getAuthPassword());
        $this->assertSame(self::VERIFIED_AT, $persistedUser->email_verified_at?->format('Y-m-d H:i:s'));
        $this->assertNull(User::find(999999));
    }

    public function test_email_change_to_an_existing_address_is_rejected(): void
    {
        $user = $this->createUser(
            email: 'unique-source@example.test',
            referralCode: 'UNIQUESOURCE',
        );
        $this->createUser(
            email: 'already-used@example.test',
            referralCode: 'ALREADYUSED',
        );

        $response = $this->actingAs($user)
            ->from(route('aura.settings'))
            ->post(route('aura.settings.profile.update'), [
                'name' => $user->name,
                'email' => 'ALREADY-USED@example.test',
                'current_password' => self::CURRENT_PASSWORD,
            ]);

        $response->assertRedirect(route('aura.settings'));
        $response->assertSessionHasErrorsIn('profileUpdate', [
            'email' => 'Adresa de email este deja utilizată.',
        ]);
        $this->assertSame('unique-source@example.test', $user->fresh()->email);
        $this->assertSame(self::VERIFIED_AT, $user->fresh()->email_verified_at?->format('Y-m-d H:i:s'));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createUser(string $email, string $referralCode, array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'name' => 'Original Name',
            'email' => $email,
            'email_verified_at' => self::VERIFIED_AT,
            'password' => Hash::make(self::CURRENT_PASSWORD),
            'role' => 'user',
            'status' => 'active',
            'referral_code' => $referralCode,
        ], $attributes));
    }
}
