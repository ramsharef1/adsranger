<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Wave21GrowthAndIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_wave21_public_growth_pages_render(): void
    {
        $this->get('/calculator')->assertOk();
        $this->get('/en/calculator')->assertOk();
        $this->get('/partners')->assertOk()->assertSee('Riyadh Growth Partners');
        $this->get('/en/partners')->assertOk();
        $this->get('/resources')->assertOk()->assertSee('Ramadan');
        $this->get('/en/resources')->assertOk();
    }
}
