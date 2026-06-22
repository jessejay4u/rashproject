<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Services\JWTService;

class AuthServiceTest extends TestCase
{
    private JWTService $jwt;

    protected function setUp(): void
    {
        parent::setUp();
        $_ENV['JWT_SECRET']         = 'test-secret-key-32-chars-long-ok-x';
        $_ENV['JWT_REFRESH_SECRET'] = 'test-refresh-32-chars-long-ok-xx';
        $_ENV['JWT_ACCESS_TTL']     = '900';
        $_ENV['JWT_REFRESH_TTL']    = '2592000';
        $_ENV['APP_URL']            = 'http://localhost';
        $this->jwt = new JWTService();
    }

    public function test_access_token_generated_and_verified(): void
    {
        $payload = [
            'sub'   => 'user-uuid-1234',
            'email' => 'test@healthplatform.org',
            'role'  => 'data_entry',
        ];

        $token   = $this->jwt->generateAccessToken($payload);
        $decoded = $this->jwt->verifyAccessToken($token);

        $this->assertSame('user-uuid-1234', $decoded['sub']);
        $this->assertSame('test@healthplatform.org', $decoded['email']);
    }

    public function test_refresh_token_generated_and_verified(): void
    {
        $payload = ['sub' => 'user-uuid-5678'];
        $token   = $this->jwt->generateRefreshToken($payload);
        $decoded = $this->jwt->verifyRefreshToken($token);

        $this->assertSame('user-uuid-5678', $decoded['sub']);
        $this->assertSame('refresh', $decoded['type']);
    }

    public function test_expired_token_throws(): void
    {
        // Generate token with -1 expiry
        $payload = ['sub' => 'test', 'exp' => time() - 1, 'iat' => time(), 'iss' => 'test', 'nbf' => time() - 2, 'jti' => 'x'];
        $token   = \Firebase\JWT\JWT::encode($payload, $_ENV['JWT_SECRET'], 'HS256');

        $this->expectException(\RuntimeException::class);
        $this->jwt->verifyAccessToken($token);
    }

    public function test_tampered_token_throws(): void
    {
        $payload = ['sub' => 'test'];
        $token   = $this->jwt->generateAccessToken($payload);
        $tampered = $token . 'tampered';

        $this->expectException(\RuntimeException::class);
        $this->jwt->verifyAccessToken($tampered);
    }

    public function test_wrong_secret_throws(): void
    {
        $_ENV['JWT_SECRET'] = 'wrong-secret-key-32-chars-long-xxxx';
        $wrongJwt = new JWTService();

        $_ENV['JWT_SECRET'] = 'test-secret-key-32-chars-long-ok-x';
        $token = $this->jwt->generateAccessToken(['sub' => 'test']);

        $this->expectException(\RuntimeException::class);
        $wrongJwt->verifyAccessToken($token);
    }
}
