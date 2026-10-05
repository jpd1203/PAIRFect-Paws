<?php

namespace App\Support;

final class CsvSafeCell
{
    public static function text(?string $value): string
    {
        $value ??= '';

        return preg_match('/^[\s]*[=+@\-]/u', $value) ? "'".$value : $value;
    }
}
