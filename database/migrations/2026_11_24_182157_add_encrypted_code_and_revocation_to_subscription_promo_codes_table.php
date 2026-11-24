<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_promo_codes', function (Blueprint $table): void {
            $table->text('encrypted_code')->nullable()->after('code_hash');
            $table->dateTime('revoked_at')->nullable()->after('redeemed_at');
            $table->foreignId('revoked_by')->nullable()->after('revoked_at')->constrained('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('subscription_promo_codes', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('revoked_by');
            $table->dropColumn(['encrypted_code', 'revoked_at']);
        });
    }
};
