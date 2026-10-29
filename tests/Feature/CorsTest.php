<?php

namespace Tests\Feature;

use Tests\TestCase;

class CorsTest extends TestCase
{
    private function preflight(string $origin)
    {
        return $this->call('OPTIONS', '/api/v1/auth/login', [], [], [], [
            'HTTP_ORIGIN' => $origin,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'content-type,authorization,accept',
        ]);
    }

    public function test_preflight_allows_the_web_app_origin(): void
    {
        $this->preflight('https://app.faracodes.ir')
            ->assertHeader('Access-Control-Allow-Origin', 'https://app.faracodes.ir');
    }

    public function test_preflight_does_not_allow_other_origins(): void
    {
        $this->preflight('https://evil.example')->assertHeaderMissing('Access-Control-Allow-Origin');
    }
}
