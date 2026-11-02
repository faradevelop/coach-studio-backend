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

    public function test_preflight_never_echoes_a_foreign_origin(): void
    {
        $response = $this->preflight('https://evil.example');

        $this->assertNotSame(
            'https://evil.example',
            $response->headers->get('Access-Control-Allow-Origin')
        );
    }
}
