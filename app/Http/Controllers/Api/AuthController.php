<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Services\AuthService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        protected AuthService $authService
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->register($request->validated());

            return $this->success($result, 'Registered successfully', 201);
        } catch (\Throwable $e) {
            return $this->error('Registration failed', 500);
        }
    }

    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->login($request->validated());

            return $this->success($result, 'Logged in successfully');
        } catch (ValidationException $e) {
            return $this->error('Invalid credentials', 401);
        } catch (\Throwable $e) {
            return $this->error('Login failed', 500);
        }
    }

    public function logout(): JsonResponse
    {
        try {
            $this->authService->logout();

            return $this->success(null, 'Logged out successfully');
        } catch (\Throwable $e) {
            return $this->error('Logout failed', 500);
        }
    }

public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
{
    try {
        $this->authService->sendOtp($request->validated('email'));

        return $this->success(null, 'OTP sent to your email');
    } catch (\Throwable $e) {
        return $this->error($e->getMessage(), 500); // مؤقت للتشخيص بس
    }
}

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        try {
            $this->authService->resetPassword($request->validated());

            return $this->success(null, 'Password reset successfully');
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->error('Password reset failed', 500);
        }
    }
}
