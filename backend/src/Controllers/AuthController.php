<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuthService;
use App\Middleware\AuthMiddleware;
use App\Middleware\RateLimitMiddleware;
use App\Helpers\Response;
use App\Helpers\Validator;

class AuthController
{
    public function __construct(
        private readonly AuthService       $authService = new AuthService(),
        private readonly AuthMiddleware    $auth        = new AuthMiddleware(),
        private readonly RateLimitMiddleware $rateLimit = new RateLimitMiddleware()
    ) {}

    public function login(): void
    {
        $this->rateLimit->handle('', 20, 60);

        $body = $this->parseBody();
        $v    = Validator::make($body, [
            'email'    => 'required|email',
            'password' => 'required|string|min:1',
        ]);

        if ($v->fails()) {
            Response::validationError($v->errors());
            return;
        }

        try {
            $result = $this->authService->login(
                $body['email'],
                $body['password'],
                $body['device_info'] ?? []
            );
            Response::success($result, 'Login successful');
        } catch (\RuntimeException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function refresh(): void
    {
        $body = $this->parseBody();

        if (empty($body['refresh_token'])) {
            Response::error('Refresh token required', 400);
            return;
        }

        try {
            $result = $this->authService->refreshTokens($body['refresh_token']);
            Response::success($result, 'Token refreshed');
        } catch (\RuntimeException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 401);
        }
    }

    public function logout(): void
    {
        $user = $this->auth->handle();
        $body = $this->parseBody();

        if (!empty($body['refresh_token'])) {
            $this->authService->logout($user['id'], $body['refresh_token']);
        }

        Response::success(null, 'Logged out successfully');
    }

    public function me(): void
    {
        $user = $this->auth->handle();
        unset($user['password_hash'], $user['mfa_secret'], $user['mfa_backup_codes']);
        Response::success($user, 'User profile');
    }

    public function changePassword(): void
    {
        $user = $this->auth->handle();
        $body = $this->parseBody();

        $v = Validator::make($body, [
            'current_password' => 'required|string',
            'new_password'     => 'required|string|min:10',
        ]);

        if ($v->fails()) {
            Response::validationError($v->errors());
            return;
        }

        try {
            $this->authService->changePassword($user['id'], $body['current_password'], $body['new_password']);
            Response::success(null, 'Password changed successfully');
        } catch (\RuntimeException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function forgotPassword(): void
    {
        $this->rateLimit->handle('', 5, 60);
        $body = $this->parseBody();

        $v = Validator::make($body, ['email' => 'required|email']);
        if ($v->fails()) {
            Response::validationError($v->errors());
            return;
        }

        $this->authService->requestPasswordReset($body['email']);
        Response::success(null, 'If the email exists, a reset link has been sent.');
    }

    public function resetPassword(): void
    {
        $body = $this->parseBody();

        $v = Validator::make($body, [
            'token'        => 'required|string',
            'new_password' => 'required|string|min:10',
        ]);

        if ($v->fails()) {
            Response::validationError($v->errors());
            return;
        }

        try {
            $this->authService->resetPassword($body['token'], $body['new_password']);
            Response::success(null, 'Password reset successful. Please log in.');
        } catch (\RuntimeException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    private function parseBody(): array
    {
        $raw = file_get_contents('php://input');
        return json_decode($raw ?: '{}', true) ?? [];
    }
}
