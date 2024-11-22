<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $json = '{
    "update_id": 815928615,
    "message": {
        "message_id": 12,
        "from": {
            "id": 1717491249,
            "is_bot": false,
            "first_name": "Darya",
            "username": "javascript_fairy",
            "language_code": "ru"
        },
        "chat": {
            "id": 1717491249,
            "first_name": "Darya",
            "username": "javascript_fairy",
            "type": "private"
        },
        "date": 1732294417,
        "text": "/gg",
        "entities": [
            {
                "offset": 0,
                "length": 3,
                "type": "bot_command"
            }
        ]
    }
}';
        $response = $this->post('/webhook', json_decode($json, associative: true));

        $response->assertStatus(200);
    }
}
