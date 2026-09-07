<?php

it('reports a healthy application on the built-in health endpoint', function () {
    $this->get('/up')->assertOk();
});

it('answers the API ping with the standard success envelope', function () {
    $response = $this->getJson('/api/v1/ping');

    $response
        ->assertOk()
        ->assertJsonStructure([
            'data' => ['service', 'version', 'status'],
            'meta' => ['request_id'],
        ])
        ->assertJsonPath('data.service', 'opsflow-api');

    expect($response->headers->get('X-Request-ID'))
        ->toBe($response->json('meta.request_id'));
});

it('echoes a client supplied X-Request-ID header', function () {
    $response = $this->getJson('/api/v1/ping', ['X-Request-ID' => 'known-request-id']);

    $response
        ->assertOk()
        ->assertHeader('X-Request-ID', 'known-request-id')
        ->assertJsonPath('meta.request_id', 'known-request-id');
});
