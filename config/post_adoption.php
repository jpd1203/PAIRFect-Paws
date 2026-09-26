<?php

$configuredBinary = env('C2PATOOL_PATH');
$defaultWindowsBinary = base_path('tools/c2patool/bin/c2patool/c2patool.exe');
$binary = is_string($configuredBinary) && trim($configuredBinary) !== ''
    ? $configuredBinary
    : (PHP_OS_FAMILY === 'Windows' ? $defaultWindowsBinary : '');

$configuredSettings = env('C2PA_SETTINGS_PATH');
$settings = is_string($configuredSettings) && trim($configuredSettings) !== ''
    ? $configuredSettings
    : base_path('config/c2pa-settings.json');

$configuredTrustAnchors = env('C2PA_TRUST_ANCHORS_PATH');
$trustAnchors = is_string($configuredTrustAnchors) && trim($configuredTrustAnchors) !== ''
    ? $configuredTrustAnchors
    : storage_path('app/c2pa/c2pa-trust-list.pem');

$requireTrustedTimestamp = filter_var(
    env('C2PA_REQUIRE_TRUSTED_TIMESTAMP', true),
    FILTER_VALIDATE_BOOL,
    FILTER_NULL_ON_FAILURE,
);

return [
    'photo_disk' => env('POST_ADOPTION_PHOTO_DISK', 'local'),
    'maximum_photo_kilobytes' => (int) env('POST_ADOPTION_MAXIMUM_PHOTO_KILOBYTES', 10_240),
    'video_disk' => env('POST_ADOPTION_VIDEO_DISK', env('POST_ADOPTION_PHOTO_DISK', 'local')),
    'maximum_video_kilobytes' => (int) env('POST_ADOPTION_MAXIMUM_VIDEO_KILOBYTES', 12_288),

    'time_travel' => [
        // Temporary, post-adoption-only testing clock. It is disabled by default.
        'enabled' => filter_var(
            env('POST_ADOPTION_TIME_TRAVEL_ENABLED', false),
            FILTER_VALIDATE_BOOL,
            FILTER_NULL_ON_FAILURE,
        ) ?? false,
    ],

    'capture' => [
        // Capture challenges are deliberately fixed to five minutes.
        'challenge_ttl_seconds' => 300,
        'maximum_capture_age_seconds' => (int) env('POST_ADOPTION_MAXIMUM_CAPTURE_AGE_SECONDS', 300),
        'client_clock_skew_seconds' => (int) env('POST_ADOPTION_CAPTURE_CLOCK_SKEW_SECONDS', 120),
        'required_video_duration_seconds' => (float) env('POST_ADOPTION_VIDEO_DURATION_SECONDS', 3),
        'video_duration_tolerance_seconds' => (float) env('POST_ADOPTION_VIDEO_DURATION_TOLERANCE_SECONDS', 0.75),
        'ffprobe_path' => env('FFPROBE_PATH', ''),
        'ffprobe_timeout_seconds' => (int) env('FFPROBE_TIMEOUT_SECONDS', 5),
    ],

    'c2pa' => [
        'binary' => $binary,
        'settings' => $settings,
        'trust_anchors' => $trustAnchors,
        'timeout_seconds' => (int) env('C2PA_TIMEOUT_SECONDS', 20),
        'max_output_bytes' => (int) env('C2PA_MAX_OUTPUT_BYTES', 2_097_152),
        'max_signing_age_minutes' => (int) env('C2PA_MAX_SIGNING_AGE_MINUTES', 15),
        'future_skew_seconds' => (int) env('C2PA_FUTURE_SKEW_SECONDS', 120),
        // An invalid environment value must strengthen, never weaken, the policy.
        'require_trusted_timestamp' => $requireTrustedTimestamp ?? true,
    ],

    'welfare' => [
        'negative_keywords' => [
            'animal abuse',
            'physically harmed',
            'hit the pet',
            'kicked the pet',
            'not being fed',
            'refuses all food',
            'no access to clean water',
            'untreated injury',
            'untreated wound',
            'chained continuously',
            'kept in a cage all day',
            'no shelter',
            'abandoned the pet',
            'severe aggression',
            'bit someone',
            'attacked someone',
        ],
        'negative_answers' => [
            'pet_current_status' => ['poor'],
            'behavioral_observations' => ['anxious/stressed', 'aggressive'],
            'living_conditions' => ['outdoor only'],
            'eating_habits' => ['reduced appetite', 'not eating'],
            'has_clean_water' => ['no'],
            'receives_regular_meals' => ['no'],
            'living_environment_safe' => ['no'],
            'receiving_needed_veterinary_care' => ['no'],
        ],
    ],
];
