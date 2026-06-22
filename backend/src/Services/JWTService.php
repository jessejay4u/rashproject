<?php

declare(strict_types=1);

namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;

class JWTService
{
    private const ALGORITHM = 'HS256';

    public function generateAccessToken(array $payload): string
    {
        $now = time();
        $ttl = (int) ($_ENV['JWT_ACCESS_TTL'] ?? 900);

        return JWT::encode([
            'iss' => $_ENV['APP_URL'],
            'iat' => $now,
            'exp' => $now + $ttl,
            'nbf' => $now,
            'jti' => bin2hex(random_bytes(16)),
            ...$payload,
        ], $_ENV['JWT_SECRET'], self::ALGORITHM);
    }

    public function generateRefreshToken(array $payload): string
    {
        $now = time();
        $ttl = (int) ($_ENV['JWT_REFRESH_TTL'] ?? 2592000);

        return JWT::encode([
            'iss'  => $_ENV['APP_URL'],
            'iat'  => $now,
            'exp'  => $now + $ttl,
            'type' => 'refresh',
            'jti'  => bin2hex(random_bytes(16)),
            ...$payload,
        ], $_ENV['JWT_REFRESH_SECRET'], self::ALGORITHM);
    }

    public function verifyAccessToken(string $token): array
    {
        try {
            $decoded = JWT::decode($token, new Key($_ENV['JWT_SECRET'], self::ALGORITHM));
            return (array) $decoded;
        } catch (ExpiredException) {
            throw new \RuntimeException('Token expired', 401);
        } catch (SignatureInvalidException) {
            throw new \RuntimeException('Invalid token signature', 401);
        } catch (\Exception) {
            throw new \RuntimeException('Invalid token', 401);
        }
    }

    public function verifyRefreshToken(string $token): array
    {
        try {
            $decoded = JWT::decode($token, new Key($_ENV['JWT_REFRESH_SECRET'], self::ALGORITHM));
            $data = (array) $decoded;

            if (($data['type'] ?? '') !== 'refresh') {
                throw new \RuntimeException('Invalid token type', 401);
            }

            return $data;
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Exception) {
            throw new \RuntimeException('Invalid refresh token', 401);
        }
    }

    public function extractTokenFromHeader(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }
        return null;
    }
}
