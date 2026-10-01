<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test the public landing page loads successfully with Phase 1B Islamic guidance and attribution.
     */
    public function test_the_public_landing_page_returns_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('ORWeMS');
        $response->assertSee('WICHOV');
        $response->assertSee('Orphans Registration');

        // Phase 1B Qur'anic guidance assertions
        $response->assertSee("Qur'anic Guidance on Caring for Orphans", false);
        $response->assertSee('93:9');
        $response->assertSee('Surah Ad-Duha');
        $response->assertSee('2:220');
        $response->assertSee('Surah Al-Baqarah');
        $response->assertSee('107:1–3');
        $response->assertSee("Surah Al-Ma'un", false);

        // Phase 1B Prophetic guidance assertions
        $response->assertSee('Prophetic Guidance');
        $response->assertSee('Sahih al-Bukhari 5304');
        $response->assertSee('I and the one who sponsors an orphan will be like this in Paradise');

        // Phase 1B Mission & Imagery assertions
        $response->assertSee('From Compassion to Responsible Action');
        $response->assertSee('orphan-care.svg');
        $response->assertSee('education-support.svg');
        $response->assertSee('community-care.svg');
        $response->assertSee('Initial Pilot Scope');

        // Phase 1B Footer attribution assertion
        $response->assertSee('https://qafamstech.com.ng/');
        $response->assertSee('Qafam Software Technology (Qafam Soft-Tech)');
    }

    /**
     * Test the dashboard foundation loads successfully for a signed-in operator.
     *
     * Phase 3A protected the dashboard, so this now signs in first. The intent
     * of the original Phase 1 test — that the dashboard shell renders — is
     * unchanged; the guest case is covered separately below and in
     * tests/Feature/Auth/AuthenticationTest.php.
     */
    public function test_the_dashboard_returns_successful_response(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSee('ORWeMS');
        $response->assertSee('Humanitarian Operations Dashboard');
        $response->assertSee('Registered Beneficiaries');
        $response->assertSee('https://qafamstech.com.ng/');
        $response->assertSee('Qafam Software Technology (Qafam Soft-Tech)');
    }

    /**
     * Phase 3A: the dashboard is no longer public.
     */
    public function test_guests_cannot_reach_the_dashboard(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
    }
}


