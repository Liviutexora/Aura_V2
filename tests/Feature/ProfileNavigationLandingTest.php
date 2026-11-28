<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ProfileNavigationLandingTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_landing_header_links_to_the_existing_active_plan_catalog(): void
    {
        $active = Plan::factory()->create(['name' => 'Plan real pentru Landing']);
        $inactive = Plan::factory()->create(['name' => 'Plan retras pentru Landing', 'is_active' => false]);

        $this->get('/')
            ->assertOk()
            ->assertSee('<a href="#abonamente" class="">Abonamente</a>', false)
            ->assertSee('<section class="public-section" id="abonamente">', false)
            ->assertSee($active->name)
            ->assertSee(route('aura.subscriptions.select', $active))
            ->assertDontSee($inactive->name);
    }

    public function test_avatar_menu_has_the_requested_order_and_demo_entries_without_links(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $this->actingAs($user)->get('/settings')
            ->assertOk()
            ->assertSeeInOrder([
                'role="menuitem">Setări cont',
                'role="menuitem">Abonamente',
                'aria-disabled="true">Securitate',
                'aria-disabled="true">Preferințe',
                'role="menuitem">Deconectare',
            ], false)
            ->assertSee('href="'.route('aura.subscriptions').'" role="menuitem"', false)
            ->assertSee('action="'.route('aura.logout').'"', false)
            ->assertSee('name="_token"', false)
            ->assertDontSee('>Abonament</a>', false);

        $this->post('/logout')->assertRedirectToRoute('aura.login');
    }

    public function test_subscription_page_keeps_its_route_and_explains_future_invoices(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $this->actingAs($user)->get('/subscriptions')
            ->assertOk()
            ->assertSee('Facturile vor fi disponibile ulterior, după integrarea Stripe.')
            ->assertDontSee('>Abonament</a>', false);
    }
}
