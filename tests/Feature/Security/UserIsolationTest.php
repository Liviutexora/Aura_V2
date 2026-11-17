<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserIsolationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const USER_A_PASSWORD = 'user-a-password-123';

    private const USER_B_PASSWORD = 'user-b-password-123';

    public function test_profile_payload_can_only_update_authenticated_user_and_ignores_sensitive_fields(): void
    {
        $userA = $this->createUser(
            name: 'User A',
            email: 'user-a-profile@example.test',
            referralCode: 'USERAPROFILE',
            password: self::USER_A_PASSWORD,
        );
        $userB = $this->createUser(
            name: 'User B',
            email: 'user-b-profile@example.test',
            referralCode: 'USERBPROFILE',
            password: self::USER_B_PASSWORD,
        );
        $userAId = $userA->getKey();
        $userBPasswordHash = $userB->getAuthPassword();

        $response = $this->actingAs($userA)
            ->from(route('aura.settings'))
            ->post(route('aura.settings.profile.update'), [
                'id' => $userB->getKey(),
                'user_id' => $userB->getKey(),
                'role' => 'super_admin',
                'status' => 'disabled',
                'name' => 'User A Updated',
                'email' => $userA->email,
            ]);

        $response->assertRedirectToRoute('aura.settings');

        $persistedUserA = User::findOrFail($userAId);
        $persistedUserB = $userB->fresh();

        $this->assertSame('User A Updated', $persistedUserA->name);
        $this->assertSame($userAId, $persistedUserA->getKey());
        $this->assertSame('user', $persistedUserA->role);
        $this->assertSame('active', $persistedUserA->status);
        $this->assertSame('User B', $persistedUserB->name);
        $this->assertSame('user-b-profile@example.test', $persistedUserB->email);
        $this->assertSame('user', $persistedUserB->role);
        $this->assertSame('active', $persistedUserB->status);
        $this->assertSame($userBPasswordHash, $persistedUserB->getAuthPassword());
    }

    public function test_password_payload_can_only_change_authenticated_users_password(): void
    {
        $userA = $this->createUser(
            name: 'User A',
            email: 'user-a-password@example.test',
            referralCode: 'USERAPASSWORD',
            password: self::USER_A_PASSWORD,
        );
        $userB = $this->createUser(
            name: 'User B',
            email: 'user-b-password@example.test',
            referralCode: 'USERBPASSWORD',
            password: self::USER_B_PASSWORD,
        );
        $userBPasswordHash = $userB->getAuthPassword();
        $newPassword = 'user-a-new-password-456';

        $response = $this->actingAs($userA)
            ->from(route('aura.settings'))
            ->post(route('aura.settings.password.update'), [
                'id' => $userB->getKey(),
                'user_id' => $userB->getKey(),
                'current_password' => self::USER_A_PASSWORD,
                'password' => $newPassword,
                'password_confirmation' => $newPassword,
            ]);

        $response->assertRedirectToRoute('aura.settings');
        $this->assertTrue(Hash::check($newPassword, $userA->fresh()->getAuthPassword()));
        $this->assertFalse(Hash::check(self::USER_A_PASSWORD, $userA->fresh()->getAuthPassword()));
        $this->assertSame($userBPasswordHash, $userB->fresh()->getAuthPassword());
        $this->assertTrue(Hash::check(self::USER_B_PASSWORD, $userB->fresh()->getAuthPassword()));
    }

    private function createUser(
        string $name,
        string $email,
        string $referralCode,
        string $password,
    ): User {
        return User::factory()->create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => 'user',
            'status' => 'active',
            'referral_code' => $referralCode,
        ]);
    }
}
