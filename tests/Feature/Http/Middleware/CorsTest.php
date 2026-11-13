<?php

namespace Tests\Feature\Http\Middleware;

use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class CorsTest extends TestCase
{
    private const string FOREIGN_ORIGIN = 'https://evil.example';

    public function test_preflight_from_a_foreign_origin_is_not_allowed(): void
    {
        $response = $this->options(route('secrets.store'), [], [
            'Origin' => self::FOREIGN_ORIGIN,
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'Content-Type',
        ]);

        $this->assertOriginNotAllowed($response);
    }

    public function test_foreign_origin_is_not_allowed_on_real_requests(): void
    {
        $response = $this->signIn()
            ->get(route('secrets.create'), ['Origin' => self::FOREIGN_ORIGIN])
            ->assertOk();

        $this->assertOriginNotAllowed($response);
    }

    public function test_own_origin_is_allowed_without_credentials(): void
    {
        $origin = config('app.url');

        $this->signIn()
            ->get(route('secrets.create'), ['Origin' => $origin])
            ->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', $origin)
            ->assertHeaderMissing('Access-Control-Allow-Credentials');
    }

    /**
     * The CORS layer only ever advertises the application's own origin, so a
     * browser on any other origin sees a mismatch and blocks the response.
     */
    private function assertOriginNotAllowed(TestResponse $response): void
    {
        $allowed = $response->headers->get('Access-Control-Allow-Origin');

        $this->assertNotSame('*', $allowed);
        $this->assertNotSame(self::FOREIGN_ORIGIN, $allowed);
        $this->assertContains($allowed, [null, config('app.url')]);

        $response->assertHeaderMissing('Access-Control-Allow-Credentials');
    }
}
