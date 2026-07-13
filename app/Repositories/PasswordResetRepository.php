<?php

namespace App\Repositories;

use App\Models\PasswordResetOtp;
use App\Repositories\Contracts\PasswordResetRepositoryInterface;

class PasswordResetRepository implements PasswordResetRepositoryInterface
{
    public function createOtp(string $email, string $otp): PasswordResetOtp
    {
        $this->deleteByEmail($email);

        return PasswordResetOtp::create([
            'email' => $email,
            'otp' => $otp,
            'expires_at' => now()->addMinutes(10),
        ]);
    }

    public function findValidOtp(string $email, string $otp): ?PasswordResetOtp
    {
        return PasswordResetOtp::where('email', $email)
            ->where('otp', $otp)
            ->where('expires_at', '>', now())
            ->first();
    }

    public function deleteByEmail(string $email): void
    {
        PasswordResetOtp::where('email', $email)->delete();
    }
}