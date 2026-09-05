<?php

namespace App\Providers;

use App\Contracts\GoogleAccessTokenProvider;
use App\Contracts\MediaVerifier;
use App\Services\GoogleApplicationDefaultCredentialsTokenProvider;
use App\Services\MediaVerificationService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(MediaVerifier::class, MediaVerificationService::class);
        $this->app->bind(
            GoogleAccessTokenProvider::class,
            GoogleApplicationDefaultCredentialsTokenProvider::class,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
