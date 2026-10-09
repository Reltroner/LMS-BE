<?php

namespace Tests\Feature;

use Tests\TestCase;

class AssistantSurfaceTest extends TestCase
{
    public function test_root_route_returns_not_found(): void
    {
        $response = $this->get('/');

        $response->assertStatus(404);
    }

    public function test_health_up_route_returns_not_found(): void
    {
        $response = $this->get('/up');

        $response->assertStatus(404);
    }
}
