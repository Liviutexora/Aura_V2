<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->select('id', 'referral_code')->chunkById(200, function (Collection $users): void {
            foreach ($users as $user) {
                if (preg_match('/\A[0-9a-f]{32}\z/', $user->referral_code)) {
                    continue;
                }

                do {
                    $code = bin2hex(random_bytes(16));
                } while (DB::table('users')->where('referral_code', $code)->exists());

                DB::table('users')->where('id', $user->id)->where('referral_code', $user->referral_code)
                    ->update(['referral_code' => $code]);
            }
        });
    }

    public function down(): void
    {
        // Keep distributed links valid when rolling back the application.
    }
};
