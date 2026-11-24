<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\SubscriptionEvent;
use App\Models\SubscriptionPromoCode;
use App\Models\User;
use App\Models\UserMembership;
use App\Services\MembershipService;
use Closure;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class CouponConcurrencyTest extends TestCase
{
    public function test_two_accounts_competing_for_one_coupon_receive_one_membership(): void
    {
        $database = config('database.connections.mysql.database');
        if (DB::getDefaultConnection() !== 'mysql' || ! preg_match('/^aura_coupon_test_[0-9a-f]{16}$/D', $database)) {
            $this->markTestSkipped('Necesită o bază MySQL de test dedicată, aura_coupon_test_ urmat de 16 cifre hexazecimale.');
        }
        $this->assertSame($database, DB::scalar('select database()'));
        $this->artisan('migrate', ['--database' => 'mysql'])->assertSuccessful();
        $plan = Plan::factory()->create();
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $users = User::factory()->count(2)->create(['status' => 'active']);
        $promo = app(MembershipService::class)->issueCode($admin, $plan->id, 7, null, now()->addDay()->toImmutable(), (string) Str::uuid());
        $prefix = sys_get_temp_dir().DIRECTORY_SEPARATOR.'aura-race-'.Str::uuid();
        try {
            $results = Concurrency::driver('process')->run([
                $this->competitor($database, $users[0]->id, $plan->id, $promo['code'], $prefix.'-a', $prefix.'-b'),
                $this->competitor($database, $users[1]->id, $plan->id, $promo['code'], $prefix.'-b', $prefix.'-a'),
            ], 20);
        } finally {
            foreach ([$prefix.'-a', $prefix.'-b'] as $marker) {
                if (is_file($marker)) {
                    unlink($marker);
                }
            }
        }
        sort($results);
        $this->assertSame(['activated', 'unavailable'], $results);
        $period = UserMembership::whereIn('user_id', $users->modelKeys())->sole();
        $this->assertSame($period->id, SubscriptionPromoCode::findOrFail($promo['id'])->membership_id);
        $this->assertSame(1, SubscriptionEvent::where('promo_code_id', $promo['id'])->where('type', 'activated')->count());
    }

    private function competitor(string $database, int $userId, int $planId, string $code, string $ready, string $peer): Closure
    {
        return static function () use ($database, $userId, $planId, $code, $ready, $peer): string {
            if (! app()->environment('testing') || DB::getDefaultConnection() !== 'mysql' || DB::scalar('select database()') !== $database) {
                throw new RuntimeException('Conexiunea nu indică baza izolată.');
            }
            DB::connection()->beforeExecuting(static function (string $sql) use ($ready, $peer): void {
                if (! str_contains($sql, 'subscription_promo_codes') || ! str_contains($sql, 'for update')) {
                    return;
                }
                touch($ready);
                $deadline = microtime(true) + 8;
                while (! is_file($peer)) {
                    if (microtime(true) >= $deadline) {
                        throw new RuntimeException('Al doilea proces nu a ajuns la blocarea cuponului.');
                    }
                    usleep(10000);
                    clearstatcache(true, $peer);
                }
            });
            try {
                app(MembershipService::class)->redeem(User::findOrFail($userId), $code, $planId, 'now', (string) Str::uuid());
            } catch (ValidationException $exception) {
                if (! isset($exception->errors()['code'])) {
                    throw $exception;
                }

                return 'unavailable';
            }

            return 'activated';
        };
    }
}
