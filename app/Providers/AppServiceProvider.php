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
        \App\Models\AdopterProfile::saved(function ($profile) {
            if ($profile->wasRecentlyCreated || $profile->wasChanged(['bfi_responses', 'bfi_completed_at', 'housing_type', 'has_existing_pets', 'has_children', 'financial_readiness'])) {
                app(\App\Services\Matching\ApplicationMatchService::class)->forUser($profile->user_id);
            }
        });
        \App\Models\Pet::saved(function ($pet) {
            if ($pet->wasChanged(['species', 'physical_size', 'medical_needs', 'life_stage', 'has_aggression_history', 'aggression_history_verified_at', 'high_vocalization', 'availability_status', 'is_archived'])) {
                app(\App\Services\Matching\ApplicationMatchService::class)->forPet($pet->id);
            }
        });
        $refreshAssessment = function ($record) {
            $pet = \App\Models\Pet::withoutGlobalScopes()->find($record->pet_id);
            if ($pet) {
                app(\App\Services\Matching\ApplicationMatchService::class)->refreshPetSummary($pet);
            }
        };
        \App\Models\AssessmentRecord::saved($refreshAssessment);
        \App\Models\AssessmentRecord::deleted($refreshAssessment);

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
