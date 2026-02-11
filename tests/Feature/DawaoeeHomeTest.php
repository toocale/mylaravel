<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

it('renders the dawaoee home page', function () {
    $response = $this->get('/');
    $response->assertStatus(200);
});
