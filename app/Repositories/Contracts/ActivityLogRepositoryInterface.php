<?php

namespace App\Repositories\Contracts;

interface ActivityLogRepositoryInterface
{
    public function log(int $userId, string $action, ?string $description = null): void;
}