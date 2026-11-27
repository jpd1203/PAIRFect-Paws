<?php

return [
    'enabled' => env('RESEARCH_MODE', false),
    'ocr_enabled' => env('RESEARCH_OCR_ENABLED', false),
    'retain_ocr_text' => env('RESEARCH_OCR_RETAIN_TEXT', false),
    'disposable_database' => env('PAIRFECT_DISPOSABLE_MYSQL_DB'),
];
