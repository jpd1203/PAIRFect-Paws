<?php

return [
    'provider' => env('DOCUMENT_OCR_PROVIDER', 'google_vision'),
    'google_application_credentials' => env('GOOGLE_APPLICATION_CREDENTIALS'),
    'google_vision_scope' => env('GOOGLE_CLOUD_VISION_SCOPE', 'https://www.googleapis.com/auth/cloud-platform'),
    'minimum_text_length' => (int) env('DOCUMENT_OCR_MINIMUM_TEXT_LENGTH', 25),
    'minimum_match_score' => (float) env('DOCUMENT_OCR_MINIMUM_MATCH_SCORE', 0.72),
    'minimum_name_similarity' => (float) env('DOCUMENT_OCR_MINIMUM_NAME_SIMILARITY', 0.82),
    'minimum_address_similarity' => (float) env('DOCUMENT_OCR_MINIMUM_ADDRESS_SIMILARITY', 0.60),
    'maximum_adopter_reuploads' => (int) env('DOCUMENT_MAX_ADOPTER_REUPLOADS', 1),
];
