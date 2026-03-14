<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AuthExchangeTest extends TestCase
{
    public function test_it_exchanges_a_valid_code_once(): void
    {
        $exchangeCode = 'test-exchange-code-1234567890-abcdefghij';

        Cache::put('auth_exchange:' . $exchangeCode, [
            'access_token' => 'access-token-123',
            'refresh_token' => 'refresh-token-456',
            'expires_in' => 86400,
            'refresh_expires_in' => 604800,
        ], now()->addMinutes(5));

        $response = $this->postJson('/api/v1/auth/exchange', [
            'exchange_code' => $exchangeCode,
        ]);

        $response
            ->assertOk()
            ->assertJson([
                'status' => 'success',
                'message' => 'Tokens exchanged successfully.',
                'access_token' => 'access-token-123',
                'refresh_token' => 'refresh-token-456',
                'expires_in' => 86400,
                'refresh_expires_in' => 604800,
            ]);

        $secondResponse = $this->postJson('/api/v1/auth/exchange', [
            'exchange_code' => $exchangeCode,
        ]);

        $secondResponse
            ->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'message' => 'Exchange code is invalid or expired. Please verify Discord again.',
            ]);
    }

    public function test_it_rejects_an_unknown_exchange_code(): void
    {
        $exchangeCode = 'missing-exchange-code-1234567890-abcdefgh';

        $response = $this->postJson('/api/v1/auth/exchange', [
            'exchange_code' => $exchangeCode,
        ]);

        $response
            ->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'message' => 'Exchange code is invalid or expired. Please verify Discord again.',
            ]);
    }
}
