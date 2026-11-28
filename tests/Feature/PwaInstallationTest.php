<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\UserHeaderStatus;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PwaInstallationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_manifest_exposes_the_installation_contract(): void
    {
        config()->set('pwa.enabled', true);

        $response = $this->get('/manifest.webmanifest');

        $response->assertOk()
            ->assertJson([
                'id' => '/',
                'display' => 'standalone',
                'start_url' => '/dashboard',
                'scope' => '/',
                'icons' => [
                    ['src' => asset('assets/aura/pwa/icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                    ['src' => asset('assets/aura/pwa/icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ],
            ])
            ->assertHeader('Content-Type', 'application/manifest+json')
            ->assertJsonCount(4, 'icons');

        $this->assertArrayNotHasKey('display_override', $response->json());
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_service_worker_is_versioned_and_contains_no_runtime_placeholders(): void
    {
        config()->set('pwa.worker_enabled', true);
        config()->set('pwa.cache_version', 'test-version');
        config()->set('pwa.worker_retirement', false);

        $response = $this->get('/sw.js');
        $content = $response->getContent();

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/javascript; charset=UTF-8');

        $this->assertIsString($content);
        $this->assertStringContainsString('aura-pwa-testing-test-version', $content);
        $this->assertStringNotContainsString('__AURA_CACHE_NAME__', $content);
        $this->assertStringNotContainsString('__AURA_RETIRE__', $content);
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_sensitive_pages_are_marked_private_and_uncacheable(): void
    {
        foreach (['/admin', '/dashboard'] as $path) {
            $response = $this->get($path);
            $response->assertRedirect();

            $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
            $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        }

        $this->get('/login')
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private');
    }

    public function test_authenticated_and_forbidden_responses_remain_uncacheable(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'role' => 'user',
            'status' => 'active',
        ]);

        $this->actingAs($user)->get('/settings')
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private');

        $this->get('/admin/users')
            ->assertForbidden()
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private');
    }

    public function test_guest_redirects_from_protected_user_routes_are_uncacheable(): void
    {
        foreach (['/settings', '/projects', '/missions', '/subscriptions'] as $path) {
            $this->get($path)
                ->assertRedirectToRoute('aura.login')
                ->assertHeader('Cache-Control', 'max-age=0, no-store, private');
        }
    }

    public function test_expired_session_form_response_keeps_csrf_status_and_is_uncacheable(): void
    {
        $this->app->instance('env', 'local');

        $this->post('/settings/profile', ['name' => 'Test'])
            ->assertStatus(419)
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private');
    }

    public function test_retirement_configuration_updates_existing_workers_without_install_prompt(): void
    {
        config()->set('pwa.enabled', false);
        config()->set('pwa.worker_enabled', false);
        config()->set('pwa.worker_retirement', true);

        $this->get('/login')
            ->assertOk()
            ->assertSee('assets/aura/pwa.js', false)
            ->assertDontSee('rel="manifest"', false)
            ->assertSee('"workerRetirement":true', false);
    }

    public function test_pwa_and_worker_flags_are_independent(): void
    {
        config()->set('pwa.worker_retirement', false);
        config()->set('pwa.enabled', false);
        config()->set('pwa.worker_enabled', true);

        $this->get('/login')
            ->assertOk()
            ->assertDontSee('assets/aura/pwa.js', false)
            ->assertDontSee('rel="manifest"', false);

        config()->set('pwa.enabled', true);
        config()->set('pwa.worker_enabled', false);

        $this->get('/login')
            ->assertOk()
            ->assertSee('assets/aura/pwa.js', false)
            ->assertSee('rel="manifest"', false)
            ->assertSee('"workerEnabled":false', false);

        config()->set('pwa.worker_enabled', true);

        $this->get('/login')
            ->assertOk()
            ->assertSee('"workerEnabled":true', false);
    }

    public function test_retirement_flag_is_rendered_for_an_existing_worker_update(): void
    {
        config()->set('pwa.worker_retirement', true);
        config()->set('pwa.worker_enabled', false);

        $this->get('/sw.js')
            ->assertOk()
            ->assertSee('const RETIRE = true;', false);
    }

    public function test_offline_page_and_installation_assets_exist(): void
    {
        $this->assertFileExists(public_path('offline.html'));
        $this->assertFileExists(public_path('assets/aura/pwa.js'));
        $this->assertFileExists(public_path('assets/aura/pwa/icon-192.png'));
        $this->assertFileExists(public_path('assets/aura/pwa/icon-512.png'));
        $this->assertFileExists(public_path('assets/aura/pwa/icon-maskable-192.png'));
        $this->assertFileExists(public_path('assets/aura/pwa/icon-maskable-512.png'));
    }

    public function test_active_user_pages_render_the_shared_header_install_control(): void
    {
        config()->set('pwa.enabled', true);
        $user = User::factory()->create(['status' => 'active']);

        $dashboard = $this->actingAs($user)->get('/dashboard')->assertOk()
            ->assertSee('data-pwa-header-install', false)
            ->assertSee('data-pwa-header-status', false)
            ->assertSee('Install App');
        $this->assertSame(1, substr_count($dashboard->getContent(), 'data-pwa-header-install'));

        $settings = $this->get('/settings')->assertOk()
            ->assertSee('data-pwa-header-install', false)
            ->assertSee('data-pwa-install-panel', false)
            ->assertSee('data-pwa-install', false);
        $this->assertSame(1, substr_count($settings->getContent(), 'assets/aura/pwa.js'));
    }

    public function test_header_keeps_the_original_status_when_installation_is_not_allowed(): void
    {
        config()->set('pwa.enabled', false);
        $active = User::factory()->create(['status' => 'active']);

        $this->actingAs($active)->get('/dashboard')->assertOk()
            ->assertSee('data-pwa-header-status', false)
            ->assertDontSee('data-pwa-header-install', false);

        config()->set('pwa.enabled', true);
        $suspended = User::factory()->create(['status' => 'suspended']);
        $status = app(UserHeaderStatus::class)->for($suspended);
        $this->assertSame('Cont suspendat', $status['label']);
        $this->assertFalse($status['installable']);
    }
}
