<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdoptionApplication;
use Illuminate\Support\Facades\Storage;

class AdoptionProfileController extends Controller
{
    /**
     * GET /admin/adoption-profiles — list all approved adoptions
     */
    public function index()
    {
        $profiles = AdoptionApplication::with(['user', 'pet'])
            ->where('status', 'Approved')
            ->latest()
            ->paginate(20);

        return view('admin.adoption-profile.index', compact('profiles'));
    }

    /**
     * GET /admin/adoption-profiles/{application}/document — redirect to document
     */
    public function document(AdoptionApplication $application)
    {
        abort_if(!$application->document_path, 404);

        return redirect(Storage::disk('public')->url($application->document_path));
    }
}
