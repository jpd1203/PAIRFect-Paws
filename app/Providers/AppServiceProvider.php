<?php

namespace App\Providers;

use App\Contracts\GoogleAccessTokenProvider;
use App\Contracts\MediaVerifier;
use App\Services\GoogleApplicationDefaultCredentialsTokenProvider;
use App\Services\MediaVerificationService;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
        Password::defaults(function () {
            $rule = Password::min(8)
                ->letters()
                ->mixedCase()
                ->numbers()
                ->symbols();

            return $this->app->isProduction()
                        ? $rule->uncompromised()
                        : $rule;
        });
    }
}
