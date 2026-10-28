<?php

namespace Tests\Feature;

use Tests\TestCase;

class CorsTest extends TestCase
{
    private function preflight(string $origin)
    {
        return $this->call('OPTIONS', '/api/login', server: [
            'HTTP_ORIGIN' => $origin,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'content-type',
        ]);
    }

    public function test_allows_configured_origins_and_pattern(): void
    {
        config([
            'cors.allowed_origins' => ['https://ui.example'],
            'cors.allowed_origins_patterns' => ['#^https://office-inventory-ui-.*\.vercel\.app$#'],
        ]);

        $this->preflight('https://ui.example')
            ->assertHeader('Access-Control-Allow-Origin', 'https://ui.example');
        $this->preflight('https://office-inventory-ui-abc.vercel.app')
            ->assertHeader('Access-Control-Allow-Origin', 'https://office-inventory-ui-abc.vercel.app');
        $this->preflight('https://evil.example')
            ->assertHeaderMissing('Access-Control-Allow-Origin');
    }
}
