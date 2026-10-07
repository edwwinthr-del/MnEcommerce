<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_readiness_checks_the_database_and_is_not_cached(): void
    {
        $response = $this->getJson('/api/v1/health')->assertOk()->assertExactJson(['status' => 'ok']);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_database_outage_returns_service_unavailable_without_connection_details(): void
    {
        DB::shouldReceive('select')->once()->with('SELECT 1')->andThrow(new RuntimeException('private database connection details'));
        $this->getJson('/api/v1/health')->assertServiceUnavailable()->assertExactJson(['status' => 'unavailable']);
    }
}
