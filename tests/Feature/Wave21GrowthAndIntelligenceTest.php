<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Wave21GrowthAndIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    /** D-168: the calculator, partner directory and case studies held invented figures and agencies; they redirect to the waitlist until rebuilt from real data. */
    public function test_held_back_growth_pages_redirect_to_the_waitlist(): void
    {
        $this->get('/calculator')->assertRedirect('/waitlist');
        $this->get('/en/calculator')->assertRedirect('/en/waitlist');
        $this->get('/partners')->assertOk(); // now the P-25 design-partner page (D-219); the old directory view is held
        $this->get('/resources')->assertRedirect('/waitlist');
        $this->get('/en/resources')->assertRedirect('/en/waitlist');
    }
}
