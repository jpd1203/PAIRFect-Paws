<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdoptionApplication;

class CompatibilityController extends Controller
{
    public function index()
    {
        $applications = AdoptionApplication::with(['pet', 'user'])
            ->whereNotNull('compatibility_result')
            ->latest()
            ->get();

        return view('admin.compatibility.index', compact('applications'));
    }
}
