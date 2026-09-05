<?php

return [
    'timezone' => env('RESERVATION_TIMEZONE', 'Asia/Manila'),
    'interview_timeout_hours' => (int) env('RESERVATION_INTERVIEW_TIMEOUT_HOURS', 72),
    'admin_review_grace_hours' => (int) env('RESERVATION_ADMIN_REVIEW_GRACE_HOURS', 24),
];
