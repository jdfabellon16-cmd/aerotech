<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_application_redirects_to_the_asset_operations_portal(): void
    {
        $this->withoutVite();

        $this->get('/')->assertRedirect('/app');
        $this->get('/app')->assertOk()->assertSee('id="app"', false);
    }
}
