<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use Illuminate\Cache\RateLimiter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;

/**
 * Verifies that the api route group enforces throttle:60,1 rate limiting.
 *
 * Two assertions:
 *  1. A successful response carries X-RateLimit-Limit: 60 — proving the
 *     throttle middleware is active and configured with the correct limit.
 *  2. Once the bucket is exhausted, the next request returns 429 —
 *     end-to-end proof that the middleware blocks over-limit traffic.
 *
 * Key hashing is disabled for the test to make the rate-limit key predictable
 * (no sha1), avoiding fragile reverse-engineering of internal key formats.
 */
class RateLimitTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // Disable key hashing so we can drain the limiter with a known key.
        ThrottleRequests::shouldHashKeys(false);
    }

    protected function tearDown(): void
    {
        // Restore the default (hashed keys) after the test.
        ThrottleRequests::shouldHashKeys(true);

        parent::tearDown();
    }

    public function test_api_response_includes_rate_limit_headers(): void
    {
        // Act
        $response = $this->getJson('/api/v1/debtors');

        // Assert — middleware is active and limit is 60/minute
        $response->assertStatus(200);
        $response->assertHeader('X-RateLimit-Limit', '60');
    }

    public function test_api_returns_429_when_rate_limit_is_exceeded(): void
    {
        // Arrange — first request succeeds (consumes 1 slot)
        $response = $this->getJson('/api/v1/debtors');
        $response->assertStatus(200);

        // With hashing disabled, the key is: domain|ip → "|127.0.0.1"
        $limiter = app(RateLimiter::class);
        $key = '|127.0.0.1';

        // Exhaust the remaining 59 slots programmatically (60 total − 1 used).
        for ($i = 0; $i < 59; $i++) {
            $limiter->hit($key, 60);
        }

        // Act — the 61st attempt must be throttled
        $throttled = $this->getJson('/api/v1/debtors');

        // Assert
        $throttled->assertStatus(429);
    }
}
