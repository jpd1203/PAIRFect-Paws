<?php

namespace App\Http\Controllers;

use App\Services\PhilippineLocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    public function __construct(private PhilippineLocationService $locations) {}

    public function regions(): JsonResponse
    {
        return response()->json([
            'data' => $this->locations->regions(),
            'meta' => $this->locations->metadata(),
        ]);
    }

    public function provinces(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'region_code' => ['required', 'string', 'max:10'],
        ]);
        $regionCode = $validated['region_code'];

        return response()->json([
            'data' => $this->locations->provinces($regionCode),
            'has_direct_localities' => $this->locations->directLocalities($regionCode) !== [],
        ]);
    }

    public function localities(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'region_code' => ['required', 'string', 'max:10'],
            'province_code' => ['nullable', 'string', 'max:10'],
        ]);

        return response()->json([
            'data' => $this->locations->localities(
                $validated['region_code'],
                $validated['province_code'] ?? PhilippineLocationService::DIRECT_REGION_VALUE,
            ),
        ]);
    }

    public function barangays(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'city_municipality_code' => ['required', 'string', 'max:10'],
        ]);

        return response()->json([
            'data' => $this->locations->barangays($validated['city_municipality_code']),
        ]);
    }
}
