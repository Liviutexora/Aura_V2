<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->char('email_code_digest', 64)->nullable();
            $table->timestamp('email_code_sent_at')->nullable();
            $table->unsignedTinyInteger('email_code_failures')->default(0);
            $table->unsignedTinyInteger('login_failures')->default(0);
            $table->timestamp('login_locked_until')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['email_code_digest', 'email_code_sent_at', 'email_code_failures', 'login_failures', 'login_locked_until']);
        });
    }
};
