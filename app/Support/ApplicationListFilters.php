<?php

namespace App\Support;

use App\Enums\ApplicationStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class ApplicationListFilters
{
    /** @return array{status?: string|null, from?: string|null, to?: string|null} */
    public static function validate(Request $request): array
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(array_column(ApplicationStatus::cases(), 'value'))],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        if (! empty($filters['from']) && ! empty($filters['to']) && $filters['from'] > $filters['to']) {
            throw ValidationException::withMessages(['to' => 'The end date must be on or after the start date.']);
        }

        return $filters;
    }

    /** @param array{status?: string|null, from?: string|null, to?: string|null} $filters */
    public static function apply(Builder $query, array $filters): Builder
    {
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', CarbonImmutable::createFromFormat('!Y-m-d', $filters['from'], ManilaTime::timezone())->utc());
        }
        if (! empty($filters['to'])) {
            $query->where('created_at', '<', CarbonImmutable::createFromFormat('!Y-m-d', $filters['to'], ManilaTime::timezone())->addDay()->utc());
        }

        return $query;
    }
}
