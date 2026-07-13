<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\Contracts\ActivityLogRepositoryInterface;
use App\Repositories\Contracts\PasswordResetRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Mail;
use App\Mail\OtpMail;

class AuthService
{
    public function __construct(
        protected UserRepositoryInterface $userRepository,
        protected ActivityLogRepositoryInterface $activityLogRepository,
        protected PasswordResetRepositoryInterface $passwordResetRepository
    ) {}

    public function register(array $data): array
    {
        $user = $this->userRepository->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => bcrypt($data['password']),
            'role' => 'user',
        ]);

        $token = Auth::guard('api')->login($user);

        $this->activityLogRepository->log($user->id, 'register', 'User registered successfully');

        return $this->formatAuthData($user, $token);
    }

    public function login(array $credentials): array
    {
        if (! $token = Auth::guard('api')->attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        $user = Auth::guard('api')->user();

        $this->activityLogRepository->log($user->id, 'login', 'User logged in successfully');

        return $this->formatAuthData($user, $token);
    }

    protected function formatAuthData(User $user, string $token): array
    {
        return [
            'user' => $user,
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => Auth::guard('api')->factory()->getTTL() * 60,
        ];
    }

    public function logout(): void
    {
        Auth::guard('api')->logout();
    }


    public function sendOtp(string $email): void
    {
        $otp = (string) random_int(100000, 999999);

        $this->passwordResetRepository->createOtp($email, $otp);

        Mail::to($email)->send(new OtpMail($otp));
    }

    public function resetPassword(array $data): void
    {
        $record = $this->passwordResetRepository->findValidOtp($data['email'], $data['otp']);

        if (! $record) {
            throw ValidationException::withMessages([
                'otp' => ['Invalid or expired OTP.'],
            ]);
        }

        $user = $this->userRepository->findByEmail($data['email']);

        $user->update([
            'password' => bcrypt($data['password']),
        ]);

        $this->passwordResetRepository->deleteByEmail($data['email']);

        $this->activityLogRepository->log($user->id, 'password_reset', 'Password reset successfully');
    }
}
