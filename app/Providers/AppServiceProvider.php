<?php

namespace App\Providers;

use App\Repositories\ActivityLogRepository;
use App\Repositories\Contracts\ActivityLogRepositoryInterface;
use App\Repositories\Contracts\PasswordResetRepositoryInterface;
use App\Repositories\Contracts\TrainRepositoryInterface;
use App\Repositories\Contracts\TripRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\PasswordResetRepository;
use App\Repositories\TrainRepository;
use App\Repositories\TripRepository;
use App\Repositories\UserRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(ActivityLogRepositoryInterface::class, ActivityLogRepository::class);
        $this->app->bind(PasswordResetRepositoryInterface::class, PasswordResetRepository::class);
        $this->app->bind(TrainRepositoryInterface::class, TrainRepository::class);
        $this->app->bind(TripRepositoryInterface::class, TripRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
