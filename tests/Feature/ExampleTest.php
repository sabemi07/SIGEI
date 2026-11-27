<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $this->withoutVite();

        $response = $this->get('/');

        $response->assertStatus(200)->assertSee('data-runtime="laravel"', false);
    }

    public function test_demonstration_is_available_separately_without_database_accounts(): void
    {
        $this->withoutVite();
        $this->get('/demo')->assertOk()->assertSee('data-runtime="demo"', false);
    }
}
