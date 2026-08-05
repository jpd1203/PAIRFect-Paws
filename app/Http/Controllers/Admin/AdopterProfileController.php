<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdoptionApplication;
use Illuminate\Support\Facades\Storage;

class AdopterProfileController extends Controller
{
    public function index()
    {
        $applications = AdoptionApplication::with(['pet', 'user'])->latest()->get();

        $applications->each(function (AdoptionApplication $app) {
            $app->priorHistory = AdoptionApplication::where('user_id', $app->user_id)
                ->where('id', '!=', $app->id)
                ->where('status', AdoptionApplication::STATUS_APPROVED)
                ->whereHas('checkIns')
                ->latest()
                ->first();
        });

        return view('admin.adopter-profile.index', compact('applications'));
    }

    public function document(AdoptionApplication $application)
    {
        abort_unless($application->document_path && Storage::disk('private')->exists($application->document_path), 404);

        return Storage::disk('private')->response($application->document_path);
    }
}
