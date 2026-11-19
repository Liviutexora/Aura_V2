<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('color')->nullable();
            $table->enum('visibility', ['all', 'premium']);
            $table->enum('status', ['draft', 'active', 'paused', 'archived']);
            $table->timestamps();
        });

        Schema::create('opportunities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->string('title');
            $table->text('summary');
            $table->string('provider');
            $table->string('source_label')->nullable();
            $table->text('source_url')->nullable();
            $table->string('geography')->nullable();
            $table->text('eligibility_text')->nullable();
            $table->text('instructions')->nullable();
            $table->text('terms');
            $table->string('difficulty');
            $table->unsignedInteger('estimated_minutes');
            $table->string('icon')->nullable();
            $table->string('image_path')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->date('project_due_on')->nullable();
            $table->unsignedInteger('project_duration_days')->nullable();
            $table->unsignedInteger('participation_limit')->nullable();
            $table->enum('status', ['draft', 'published', 'paused', 'archived']);
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->enum('reward_type', ['fixed', 'percentage', 'cashback', 'external', 'non_cash']);
            $table->enum('reward_unit', ['money', 'percent', 'non_cash']);
            $table->unsignedBigInteger('reward_amount_minor')->nullable();
            $table->char('reward_currency', 3)->nullable();
            $table->unsignedInteger('reward_bps')->nullable();
            $table->string('reward_label');
            $table->string('payer');
            $table->timestamps();
            $table->unique(['category_id', 'id']);
            $table->index(['category_id', 'status', 'starts_at']);
            $table->index(['status', 'ends_at']);
        });

        Schema::create('eligibility_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->foreignId('opportunity_id')->nullable()->constrained('opportunities')->restrictOnDelete();
            $table->string('name');
            $table->text('conditions_text');
            $table->string('geography')->nullable();
            $table->string('country_code')->nullable();
            $table->enum('visibility', ['all', 'premium']);
            $table->enum('check_method', ['system', 'user_attestation', 'partner_review']);
            $table->enum('status', ['draft', 'active', 'paused', 'archived']);
            $table->unsignedInteger('version')->default(1);
            $table->string('criterion_code')->nullable();
            $table->json('criterion_params')->nullable();
            $table->timestamps();
            $table->index(['category_id', 'status', 'opportunity_id']);
            $table->foreign(['category_id', 'opportunity_id'])->references(['category_id', 'id'])->on('opportunities')->restrictOnDelete();
        });

        Schema::create('mission_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('opportunity_id')->constrained('opportunities')->restrictOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('required_action')->nullable();
            $table->enum('verification_method', ['user_declaration', 'document_upload', 'admin_approval', 'provider_event']);
            $table->unsignedInteger('xp_points')->default(0);
            $table->date('deadline_on')->nullable();
            $table->enum('deadline_anchor', ['accepted', 'step_checked', 'step_approved'])->nullable();
            $table->unsignedInteger('deadline_after_days')->nullable();
            $table->foreignId('anchor_step_id')->nullable();
            if (DB::getDriverName() === 'sqlite') {
                $table->foreign('anchor_step_id')->references('id')->on('mission_template_steps')->restrictOnDelete();
            }
            $table->unsignedInteger('position');
            $table->boolean('assign_on_accept')->default(false);
            $table->boolean('is_required')->default(false);
            $table->enum('status', ['draft', 'active', 'archived']);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->unique(['opportunity_id', 'position']);
        });

        Schema::create('mission_template_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('template_id')->constrained('mission_templates')->restrictOnDelete();
            $table->unsignedInteger('position');
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('instructions')->nullable();
            $table->text('required_action')->nullable();
            $table->date('deadline_on')->nullable();
            $table->enum('deadline_anchor', ['accepted', 'step_checked', 'step_approved'])->nullable();
            $table->unsignedInteger('deadline_after_days')->nullable();
            $table->foreignId('anchor_step_id')->nullable();
            $table->foreign('anchor_step_id')->references('id')->on('mission_template_steps')->restrictOnDelete();
            $table->unsignedInteger('xp_points')->default(0);
            $table->enum('verification_method', ['user_declaration', 'document_upload', 'admin_approval', 'provider_event'])->nullable();
            $table->boolean('confirms_receipt')->default(false);
            $table->enum('status', ['draft', 'active', 'archived']);
            $table->timestamps();
            $table->unique(['template_id', 'position']);
        });

        Schema::create('projects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('opportunity_id')->constrained('opportunities')->restrictOnDelete();
            $table->string('title');
            $table->enum('source', ['self', 'admin']);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->enum('status', ['accepted', 'active', 'completed']);
            $table->dateTime('accepted_at');
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->date('due_on')->nullable();
            $table->unsignedInteger('opportunity_version');
            $table->json('offer_snapshot');
            $table->json('eligibility_snapshot');
            $table->timestamps();
            $table->unique(['user_id', 'opportunity_id']);
            $table->index(['user_id', 'status', 'accepted_at']);
        });

        Schema::create('project_missions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
            $table->foreignId('template_id')->constrained('mission_templates')->restrictOnDelete();
            $table->unsignedInteger('template_version');
            $table->unsignedInteger('position');
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('required_action')->nullable();
            $table->enum('verification_method', ['user_declaration', 'document_upload', 'admin_approval', 'provider_event']);
            $table->unsignedInteger('xp_points');
            $table->boolean('is_required')->default(false);
            $table->date('due_on')->nullable();
            $table->enum('deadline_anchor', ['accepted', 'step_checked', 'step_approved'])->nullable();
            $table->unsignedInteger('deadline_after_days')->nullable();
            $table->foreignId('anchor_step_id')->nullable();
            if (DB::getDriverName() === 'sqlite') {
                $table->foreign('anchor_step_id')->references('id')->on('project_mission_steps')->restrictOnDelete();
            }
            $table->enum('status', ['assigned', 'in_progress', 'in_review', 'needs_changes', 'completed']);
            $table->foreignId('assigned_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('assigned_at');
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['project_id', 'template_id']);
            $table->unique(['project_id', 'position']);
            $table->unique(['project_id', 'id']);
            $table->index(['status', 'due_on']);
        });

        Schema::create('project_mission_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mission_id')->constrained('project_missions')->restrictOnDelete();
            $table->foreignId('source_step_id')->nullable()->constrained('mission_template_steps')->nullOnDelete();
            $table->unsignedInteger('position');
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('instructions')->nullable();
            $table->text('required_action')->nullable();
            $table->enum('verification_method', ['user_declaration', 'document_upload', 'admin_approval', 'provider_event']);
            $table->boolean('confirms_receipt')->default(false);
            $table->unsignedInteger('xp_points');
            $table->date('due_on')->nullable();
            $table->enum('deadline_anchor', ['accepted', 'step_checked', 'step_approved'])->nullable();
            $table->unsignedInteger('deadline_after_days')->nullable();
            $table->foreignId('anchor_step_id')->nullable();
            $table->foreign('anchor_step_id')->references('id')->on('project_mission_steps')->restrictOnDelete();
            $table->enum('status', ['pending', 'in_review', 'needs_changes', 'completed']);
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['mission_id', 'position']);
            $table->unique(['mission_id', 'id']);
        });

        Schema::create('external_earnings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
            $table->string('earning_key');
            $table->string('source');
            $table->enum('status', ['pending', 'confirmed', 'rejected']);
            $table->unsignedBigInteger('amount_minor')->nullable();
            $table->char('currency', 3)->nullable();
            $table->text('description')->nullable();
            $table->string('provider_reference')->nullable();
            $table->dateTime('confirmed_at')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['project_id', 'earning_key']);
        });

        Schema::create('mission_submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mission_id')->constrained('project_missions')->restrictOnDelete();
            $table->foreignId('step_id')->nullable()->constrained('project_mission_steps')->restrictOnDelete();
            $table->foreignId('external_earning_id')->nullable()->constrained('external_earnings')->restrictOnDelete();
            $table->enum('method', ['user_declaration', 'document_upload', 'admin_approval', 'provider_event']);
            $table->enum('status', ['pending', 'approved', 'rejected']);
            $table->foreignId('submitted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('submitted_at');
            $table->text('evidence_text')->nullable();
            $table->string('private_file_key')->nullable();
            $table->string('provider')->nullable();
            $table->string('external_event_id')->nullable();
            $table->string('request_key')->unique();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();
            $table->foreign(['mission_id', 'step_id'])->references(['mission_id', 'id'])->on('project_mission_steps')->restrictOnDelete();
            $table->unique(['provider', 'external_event_id']);
            $table->index(['status', 'submitted_at']);
        });

        Schema::create('project_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
            $table->foreignId('mission_id')->nullable()->constrained('project_missions')->restrictOnDelete();
            $table->foreignId('step_id')->nullable()->constrained('project_mission_steps')->restrictOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('event_type');
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();
            $table->string('event_key')->unique();
            $table->dateTime('occurred_at');
            $table->json('metadata')->nullable();
            $table->foreign(['project_id', 'mission_id'])->references(['project_id', 'id'])->on('project_missions')->restrictOnDelete();
            $table->foreign(['mission_id', 'step_id'])->references(['mission_id', 'id'])->on('project_mission_steps')->restrictOnDelete();
            $table->index(['project_id', 'occurred_at', 'id']);
        });

        Schema::create('mission_reminders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mission_id')->unique()->constrained('project_missions')->restrictOnDelete();
            $table->dateTime('remind_at');
            $table->string('timezone');
            $table->enum('status', ['scheduled', 'sent', 'cancelled']);
            $table->dateTime('sent_at')->nullable();
            $table->unsignedInteger('revision')->default(1);
            $table->timestamps();
            $table->index(['status', 'remind_at']);
        });

        if (DB::getDriverName() === 'mysql') {
            Schema::table('mission_templates', function (Blueprint $table): void {
                $table->foreign('anchor_step_id')->references('id')->on('mission_template_steps')->restrictOnDelete();
            });
            Schema::table('project_missions', function (Blueprint $table): void {
                $table->foreign('anchor_step_id')->references('id')->on('project_mission_steps')->restrictOnDelete();
            });
            DB::statement('ALTER TABLE opportunities ADD CONSTRAINT chk_opportunities_1 CHECK (ends_at IS NULL OR ends_at > starts_at)');
            DB::statement('ALTER TABLE opportunities ADD CONSTRAINT chk_opportunities_2 CHECK (participation_limit IS NULL OR participation_limit > 0)');
            DB::statement('ALTER TABLE opportunities ADD CONSTRAINT chk_opportunities_3 CHECK (project_due_on IS NULL OR project_duration_days IS NULL)');
            DB::statement("ALTER TABLE opportunities ADD CONSTRAINT chk_opportunities_4 CHECK ((reward_unit = 'money' AND reward_amount_minor IS NOT NULL AND reward_currency IS NOT NULL AND reward_bps IS NULL) OR (reward_unit = 'percent' AND reward_bps IS NOT NULL AND reward_bps <= 10000 AND reward_amount_minor IS NULL AND reward_currency IS NULL) OR (reward_unit = 'non_cash' AND reward_amount_minor IS NULL AND reward_currency IS NULL AND reward_bps IS NULL))");
            DB::statement('ALTER TABLE project_events ADD CONSTRAINT chk_project_events_1 CHECK (step_id IS NULL OR mission_id IS NOT NULL)');
            DB::statement("ALTER TABLE external_earnings ADD CONSTRAINT chk_external_earnings_1 CHECK (status <> 'confirmed' OR (amount_minor IS NOT NULL AND amount_minor > 0 AND currency IS NOT NULL))");
            DB::statement("ALTER TABLE projects ADD CONSTRAINT chk_projects_1 CHECK ((status = 'accepted' AND started_at IS NULL AND completed_at IS NULL) OR (status = 'active' AND started_at IS NOT NULL AND completed_at IS NULL) OR (status = 'completed' AND started_at IS NOT NULL AND completed_at IS NOT NULL))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            Schema::table('mission_templates', fn (Blueprint $table) => $table->dropForeign(['anchor_step_id']));
            Schema::table('project_missions', fn (Blueprint $table) => $table->dropForeign(['anchor_step_id']));
        }
        foreach (['mission_reminders', 'project_events', 'mission_submissions', 'external_earnings', 'project_mission_steps', 'project_missions', 'projects', 'mission_template_steps', 'mission_templates', 'eligibility_rules', 'opportunities', 'categories'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
