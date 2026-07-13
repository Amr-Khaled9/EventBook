<?php

namespace App\Repositories\Contracts;

use App\Models\PasswordResetOtp;

interface PasswordResetRepositoryInterface
{
    public function createOtp(string $email, string $otp): PasswordResetOtp;

    public function findValidOtp(string $email, string $otp): ?PasswordResetOtp;

    public function deleteByEmail(string $email): void;
}