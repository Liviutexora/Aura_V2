<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\EligibilityRule;
use App\Models\ExternalEarning;
use App\Models\MissionSubmission;
use App\Models\MissionTemplate;
use App\Models\Opportunity;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProjectWorkflowTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_empty_project_pages_show_real_empty_lists_and_admin_forms(): void
    {
        $admin = User::factory()->create(['referral_code' => (string) Str::uuid(), 'role' => 'super_admin', 'status' => 'active']);
        $this->actingAs($admin);
        foreach (['categories', 'eligibility', 'opportunities', 'missions', 'verification', 'projects', 'mission-templates'] as $resource) {
            $this->get('/admin/'.$resource)->assertOk()->assertDontSee('OPP-001');
        }
        foreach (['categories', 'eligibility', 'opportunities', 'missions', 'projects', 'mission-templates'] as $resource) {
            $this->get('/admin/'.$resource.'/create')->assertOk()->assertSee('Salvează');
        }
        $this->get('/admin')->assertOk();
        $this->get('/opportunities')->assertOk()->assertSee('Nicio oportunitate găsită');
        $this->get('/missions')->assertOk()->assertSee('Nu ai misiuni');
        $this->get('/projects')->assertOk()->assertSee('Nu ai proiecte');
        $this->get('/dashboard')->assertOk()->assertSee('Nu ai misiuni în progres.');
        $this->get('/opportunities/demo')->assertNotFound();
    }

    public function test_published_campaign_tracks_individual_deadlines_reviews_and_distinct_receipts(): void
    {
        $admin = User::factory()->create(['referral_code' => (string) Str::uuid(), 'role' => 'super_admin', 'status' => 'active']);
        $owner = User::factory()->create(['referral_code' => (string) Str::uuid(), 'status' => 'active']);
        $other = User::factory()->create(['referral_code' => (string) Str::uuid(), 'status' => 'active']);
        Storage::fake('local');
        $this->actingAs($admin)->post('/admin/categories', ['name' => 'Servicii', 'status' => 'active', 'visibility' => 'all'])->assertSessionHasNoErrors();
        $offerData = ['category_id' => Category::sole()->id, 'title' => 'Campania de primăvară', 'summary' => 'Descriere', 'provider' => 'Furnizor', 'terms' => 'Regulament',
            'difficulty' => 'Ușor', 'estimated_minutes' => 15, 'starts_at' => now()->subDay()->toDateString(), 'project_duration_days' => 60, 'participation_limit' => 1,
            'reward_type' => 'fixed', 'reward_unit' => 'money', 'reward_amount_minor' => 10000, 'reward_currency' => 'RON', 'reward_label' => '100 lei', 'payer' => 'Furnizor', 'status' => 'draft'];
        $this->post('/admin/opportunities', $offerData)->assertSessionHasNoErrors();
        $offer = Opportunity::sole();
        $templateData = ['steps_complete' => 1, 'opportunity_id' => $offer->id, 'title' => 'Înscriere și confirmare', 'verification_method' => 'user_declaration', 'assign_on_accept' => 1, 'is_required' => 1, 'status' => 'active',
            'steps' => [['title' => 'Deschiderea contului', 'verification_method' => 'document_upload', 'confirms_receipt' => 1, 'status' => 'active'],
                ['title' => 'Confirm încasarea', 'confirms_receipt' => 1, 'status' => 'active']]];
        $this->post('/admin/mission-templates', $templateData)->assertSessionHasNoErrors();
        $template = MissionTemplate::sole();
        [$first,$last] = $template->steps;
        $templateData['steps'][0]['id'] = $first->id;
        $templateData['steps'][1] += ['id' => $last->id, 'deadline_anchor' => 'step_approved', 'deadline_after_days' => 30, 'anchor_step_id' => $first->id];
        $this->patch('/admin/mission-templates/'.$template->id, $templateData + ['version' => 1])->assertSessionHasNoErrors();
        $this->post('/admin/mission-templates', [
            'steps_complete' => 1, 'opportunity_id' => $offer->id, 'title' => 'Activitate opțională', 'verification_method' => 'user_declaration',
            'assign_on_accept' => 1, 'is_required' => 0, 'status' => 'active', 'deadline_anchor' => 'step_checked', 'deadline_after_days' => 30, 'anchor_step_id' => $first->id,
            'steps' => [['title' => 'Sarcină opțională', 'status' => 'active', 'deadline_anchor' => 'accepted', 'deadline_after_days' => 2]],
        ])->assertSessionHasNoErrors();
        $offerData['status'] = 'published';
        $this->patch('/admin/opportunities/'.$offer->id, $offerData + ['version' => 1])->assertSessionHasNoErrors();
        $this->get('/admin/opportunities/'.$offer->id)->assertOk();
        $this->get('/admin/mission-templates/'.$template->id.'/edit')->assertOk();
        $this->get('/admin/opportunities/export?sort=category.name')->assertDownload('opportunities.csv');
        $this->post('/admin/eligibility', ['name' => 'Condițiile campaniei', 'category_id' => $offer->category_id, 'opportunity_id' => $offer->id, 'visibility' => 'all',
            'status' => 'active', 'check_method' => 'user_attestation', 'conditions_text' => 'Confirm condițiile de participare'])->assertSessionHasNoErrors();
        $rule = EligibilityRule::sole();
        $attestation = ['attestations' => [$rule->id.':'.$rule->version]];
        $this->actingAs($owner)->get('/opportunities')->assertOk()->assertSee($offer->title);
        $this->get('/opportunities/'.$offer->id)->assertOk()->assertSee('Acceptă oportunitatea');
        $accept = '/opportunities/'.$offer->id.'/projects';
        $this->post($accept)->assertSessionHasErrors('eligibility');
        $this->post($accept, $attestation)->assertSessionHasNoErrors();
        $this->post($accept, $attestation)->assertSessionHasNoErrors();
        $project = Project::sole();
        $mission = $project->missions()->where('is_required', true)->sole();
        $optional = $project->missions()->where('is_required', false)->sole();
        $this->assertSame(now('Europe/Bucharest')->addDays(2)->toDateString(), $optional->steps()->sole()->due_on->toDateString());
        [$anchor,$receipt] = $mission->steps;
        $this->assertNull($project->started_at);
        $this->assertNull($receipt->due_on);
        $this->get('/projects/'.$project->id)->assertOk()->assertSee('Începe proiectul');
        $this->get('/missions/'.$mission->id)->assertOk();
        $this->actingAs($other)->post($accept)->assertSessionHasErrors('opportunity');
        $this->get('/projects/'.$project->id)->assertNotFound();
        $this->get('/missions/'.$mission->id)->assertNotFound();
        $this->post('/projects/'.$project->id.'/start')->assertNotFound();
        $this->get('/admin/missions')->assertForbidden();
        $this->actingAs($owner)->post('/projects/'.$project->id.'/start')->assertSessionHasNoErrors();
        $submissionUrl = '/missions/'.$mission->id.'/submissions';
        $this->post($submissionUrl, ['request_key' => (string) Str::uuid(), 'step_id' => $anchor->id, 'amount' => '250.00', 'earning_key' => (string) Str::uuid(), 'currency' => 'RON',
            'evidence' => UploadedFile::fake()->create('document.pdf', 2, 'application/pdf')])->assertSessionHasNoErrors();
        $attempt = MissionSubmission::sole();
        $checkedDue = $optional->fresh()->due_on->toDateString();
        $this->assertSame(now('Europe/Bucharest')->addDays(30)->toDateString(), $checkedDue);
        $this->travel(2)->days();
        $this->assertNull($receipt->fresh()->due_on);
        $this->assertSame('pending', $attempt->earning->status);
        $this->get('/mission-submissions/'.$attempt->id.'/evidence')->assertOk();
        $this->actingAs($other)->get('/mission-submissions/'.$attempt->id.'/evidence')->assertNotFound();
        $this->actingAs($admin)->get('/admin/verification/'.$attempt->id)->assertOk();
        $this->patch('/admin/verification/'.$attempt->id, ['status' => 'rejected'])->assertSessionHasErrors('review_note');
        $this->patch('/admin/verification/'.$attempt->id, ['status' => 'rejected', 'review_note' => 'Document incomplet'])->assertSessionHasNoErrors();
        $this->actingAs($owner)->post($submissionUrl, ['request_key' => (string) Str::uuid(), 'step_id' => $anchor->id,
            'evidence' => UploadedFile::fake()->create('complet.pdf', 2, 'application/pdf')])->assertSessionHasNoErrors();
        $retry = MissionSubmission::latest('id')->firstOrFail();
        $this->actingAs($admin)->patch('/admin/verification/'.$retry->id, ['status' => 'approved'])->assertSessionHasNoErrors();
        $this->assertSame(now('Europe/Bucharest')->addDays(30)->toDateString(), $receipt->fresh()->due_on->toDateString());
        $this->assertSame($checkedDue, $optional->fresh()->due_on->toDateString());
        $this->assertSame($admin->id, $retry->fresh()->reviewed_by);
        $this->assertSame($attempt->external_earning_id, $retry->external_earning_id);
        $this->assertSame('confirmed', $attempt->earning->fresh()->status);
        $this->actingAs($owner);
        $reminder = '/missions/'.$mission->id.'/reminder';
        $this->put($reminder, ['remind_at' => now()->addDays(2)->format('Y-m-d\TH:i')])->assertSessionHasNoErrors();
        $this->assertSame('scheduled', $mission->reminder()->sole()->status);
        $revision = $mission->reminder()->sole()->revision;
        $this->put($reminder, ['remind_at' => now()->addDays(2)->format('Y-m-d\TH:i')])->assertSessionHasNoErrors();
        $this->assertSame($revision, $mission->reminder()->sole()->revision);
        $this->travel(3)->days();
        $this->artisan('schedule:run')->assertExitCode(0);
        $this->artisan('schedule:run')->assertExitCode(0);
        $this->assertSame(1, $project->events()->where('event_type', 'reminder_sent')->count());
        $this->delete($reminder)->assertSessionHasNoErrors();
        $check = '/missions/'.$mission->id.'/steps/'.$receipt->id;
        $payment = ['request_key' => (string) Str::uuid(), 'checked' => 1, 'amount' => '100.00', 'currency' => 'RON', 'earning_key' => (string) Str::uuid()];
        $this->patch($check, $payment)->assertSessionHasNoErrors();
        $this->patch($check, $payment)->assertSessionHasNoErrors();
        $this->assertSame('completed', $project->fresh()->status);
        $this->assertSame('completed', $mission->fresh()->status);
        $this->assertSame('assigned', $optional->fresh()->status);
        $this->assertSame(1, $project->events()->where('event_type', 'project_completed')->count());
        $this->post('/projects/'.$project->id.'/external-earnings', ['amount' => '250', 'currency' => 'RON', 'earning_key' => (string) Str::uuid()])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('external_earnings', 3);
        $this->assertEquals(60000, ExternalEarning::sum('amount_minor'));
        $this->get('/missions/'.$mission->id)->assertOk()->assertSee('Document incomplet');
        $this->actingAs($admin)->get('/admin/projects/'.$project->id)->assertOk();
        $this->get('/admin/missions/'.$mission->id)->assertOk();
        $this->get('/admin/missions?sort=project.user.name')->assertOk()->assertSee($owner->name);
        $this->get('/admin/verification?sort=mission.project.user.name')->assertOk()->assertSee($owner->name);
        $this->patch('/admin/opportunities/'.$offer->id.'/archive', ['version' => 1])->assertConflict();
        $this->patch('/admin/opportunities/'.$offer->id.'/archive', ['version' => $offer->fresh()->version])->assertSessionHasNoErrors();
        $this->actingAs($other)->get('/opportunities')->assertDontSee($offer->title);
        $this->actingAs($owner)->get('/opportunities/'.$offer->id)->assertOk()->assertSee('Deschide proiectul');
    }
}
