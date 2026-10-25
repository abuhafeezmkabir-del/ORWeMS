<?php

namespace Tests\Feature\Demo;

use Tests\TestCase;

/**
 * DemoDataTest
 *
 * Validates that all demo screens render synthetic/fictional data,
 * that simulated actions return "not persisted" notices, and that
 * no real data appears in any demo view.
 */
class DemoDataTest extends TestCase
{
    public function test_dashboard_renders_synthetic_beneficiaries(): void
    {
        $this->post(route('demo.login.post'), [
            'email' => 'demo@wicov.org',
            'password' => 'demo123',
        ]);

        $response = $this->get(route('demo.dashboard'));
        $response->assertStatus(200);
        $response->assertSee('Amina K.');
        $response->assertSee('Samuel O.');
        $response->assertSee('BEN-2026-001');
        $response->assertSee('DEMONSTRATION MODE');
    }

    public function test_beneficiary_profile_renders_fictional_data(): void
    {
        $this->post(route('demo.login.post'), [
            'email' => 'demo@wicov.org',
            'password' => 'demo123',
        ]);

        $response = $this->get(route('demo.beneficiary', 'BEN-2026-001'));
        $response->assertStatus(200);
        $response->assertSee('Amina K.');
        $response->assertSee('Grace N.');
        $response->assertSee('Foster Care');
    }

    public function test_nonexistent_beneficiary_returns_404(): void
    {
        $this->post(route('demo.login.post'), [
            'email' => 'demo@wicov.org',
            'password' => 'demo123',
        ]);

        $this->get(route('demo.beneficiary', 'BEN-9999-999'))
            ->assertStatus(404);
    }

    public function test_care_placement_history_is_simulated(): void
    {
        $this->post(route('demo.login.post'), [
            'email' => 'demo@wicov.org',
            'password' => 'demo123',
        ]);

        $response = $this->get(route('demo.care.index', 'BEN-2026-001'));
        $response->assertStatus(200);
        $response->assertSee('Mrs. Esther K.');
        $response->assertSee('Grace N.');
        $response->assertSee('Initial Placement');
    }

    public function test_education_history_is_simulated(): void
    {
        $this->post(route('demo.login.post'), [
            'email' => 'demo@wicov.org',
            'password' => 'demo123',
        ]);

        $response = $this->get(route('demo.education.index', 'BEN-2026-001'));
        $response->assertStatus(200);
        $response->assertSee('Northern Community Primary');
        $response->assertSee('Primary 4');
    }

    public function test_health_records_are_simulated(): void
    {
        $this->post(route('demo.login.post'), [
            'email' => 'demo@wicov.org',
            'password' => 'demo123',
        ]);

        $response = $this->get(route('demo.health.index', 'BEN-2026-001'));
        $response->assertStatus(200);
        $response->assertSee('Nutritional Assessment');
        $response->assertSee('Immunisation Review');
    }

    public function test_health_create_simulates_no_persistence(): void
    {
        $this->post(route('demo.login.post'), [
            'email' => 'demo@wicov.org',
            'password' => 'demo123',
        ]);

        $response = $this->post(route('demo.health.store', 'BEN-2026-001'), [
            'concern' => 'Nutritional Assessment',
            'record_date' => '2026-03-10',
            'summary' => 'Simulated test entry',
        ]);

        $response->assertSessionHas('status', 'demo-action-simulated');
        $response->assertSessionHas('message');
        $this->assertStringContainsString('NOT saved', $response->getSession()->get('message'));
    }

    public function test_relationship_store_is_simulated(): void
    {
        $this->post(route('demo.login.post'), [
            'email' => 'demo@wicov.org',
            'password' => 'demo123',
        ]);

        $response = $this->post(route('demo.relationships.store', 'BEN-2026-001'), [
            'name' => 'Test Person',
            'relation' => 'Test Relation',
            'contact' => '0800-123-4567',
        ]);

        $response->assertSessionHas('status', 'demo-action-simulated');
    }

    public function test_close_workflow_is_simulated(): void
    {
        $this->post(route('demo.login.post'), [
            'email' => 'demo@wicov.org',
            'password' => 'demo123',
        ]);

        $response = $this->post(route('demo.close.confirm', 'BEN-2026-001'), [
            'record_id' => 1,
            'closure_reason' => 'Test closure reason',
        ]);

        $response->assertSessionHas('status', 'demo-action-simulated');
        $this->assertStringContainsString('no record was deleted', $response->getSession()->get('message'));
    }

    public function test_all_demo_views_show_demonstration_banner(): void
    {
        $this->post(route('demo.login.post'), [
            'email' => 'demo@wicov.org',
            'password' => 'demo123',
        ]);

        $routes = [
            route('demo.dashboard'),
            route('demo.beneficiary', 'BEN-2026-001'),
            route('demo.care.index', 'BEN-2026-001'),
            route('demo.education.index', 'BEN-2026-001'),
            route('demo.health.index', 'BEN-2026-001'),
            route('demo.health.create', 'BEN-2026-001'),
            route('demo.relationships.index', 'BEN-2026-001'),
            route('demo.close', 'BEN-2026-001'),
        ];

        foreach ($routes as $route) {
            $response = $this->get($route);
            $response->assertStatus(200);
            $response->assertSee('DEMONSTRATION MODE');
        }
    }
}