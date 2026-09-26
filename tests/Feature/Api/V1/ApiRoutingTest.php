<?php

it('merespons health check atau ping route pada api v1', function () {
    $response = $this->getJson('/api/v1/ping');

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'ok',
            'version' => 'v1',
        ]);
});
