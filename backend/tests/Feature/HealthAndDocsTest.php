<?php

it('exposes the internal health probe with service checks', function () {
    $this->get('/internal/health')
        ->assertOk()
        ->assertJsonPath('status', 'ok')
        ->assertJsonStructure([
            'status',
            'services' => ['app', 'database', 'cache'],
            'timestamp',
        ]);
});

it('serves the OpenAPI viewer and the static specification', function () {
    $this->get('/docs/api')
        ->assertOk()
        ->assertSee('swagger-ui', false);

    $response = $this->get('/openapi.yaml');

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('yaml');
    expect($response->getContent())->toContain('openapi: 3.1.0');
});

it('adds baseline security headers to responses', function () {
    $this->get('/up')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
});
