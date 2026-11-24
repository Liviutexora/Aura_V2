<?php

use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table): void {
            $table->id();
            $table->string('seed_key', 40)->nullable()->unique();
            $table->string('name', 120);
            $table->boolean('is_active')->default(true);
            $table->json('benefits');
            $table->unsignedInteger('access_level');
            $table->unsignedInteger('max_projects')->nullable();
            $table->unsignedInteger('max_explorer_projects')->nullable();
            $table->unsignedInteger('max_mentor_projects')->nullable();
            $table->unsignedSmallInteger('trial_days')->default(0);
            $table->unsignedInteger('monthly_price_minor');
            $table->unsignedInteger('quarterly_price_minor');
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });
        Schema::create('user_memberships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->enum('source', ['trial', 'manual', 'promo']);
            $table->json('terms');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->dateTime('closed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'starts_at', 'ends_at']);
            $table->index(['closed_at', 'ends_at']);
        });
        Schema::create('subscription_promo_codes', function (Blueprint $table): void {
            $table->id();
            $table->char('code_hash', 64)->unique();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('duration_days');
            $table->dateTime('expires_at')->nullable();
            $table->foreignId('reserved_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('membership_id')->nullable()->unique()->constrained('user_memberships')->restrictOnDelete();
            $table->dateTime('redeemed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('subscription_events', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 40);
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->foreignId('membership_id')->nullable()->constrained('user_memberships')->restrictOnDelete();
            $table->foreignId('promo_code_id')->nullable()->constrained('subscription_promo_codes')->restrictOnDelete();
            $table->uuid('request_key')->nullable();
            $table->unique(['actor_id', 'request_key']);
            $table->text('reason')->nullable();
            $table->json('details');
            $table->dateTime('occurred_at');
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->dateTime('trial_used_at')->nullable();
        });
        Schema::table('opportunities', function (Blueprint $table): void {
            $table->unsignedInteger('min_access_level')->default(1);
            $table->enum('participation_mode', ['explorer', 'mentor', 'both'])->default('explorer');
        });
        Schema::table('projects', function (Blueprint $table): void {
            $table->enum('participation_type', ['explorer', 'mentor'])->default('explorer');
        });
        (new SubscriptionPlanSeeder)->run();
    }

    public function down(): void
    {
        Schema::table('projects', fn (Blueprint $table) => $table->dropColumn('participation_type'));
        Schema::table('opportunities', fn (Blueprint $table) => $table->dropColumn(['min_access_level', 'participation_mode']));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('trial_used_at'));
        Schema::dropIfExists('subscription_events');
        Schema::dropIfExists('subscription_promo_codes');
        Schema::dropIfExists('user_memberships');
        Schema::dropIfExists('plans');
    }
};
