<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pet;

class AnimalController extends Controller
{
    /**
     * GET /admin/animals — list all pets including all statuses
     */
    public function index()
    {
        // withoutGlobalScope reveals archived pets too — here we show all
        $pets = Pet::withoutGlobalScope('notArchived')
            ->with('branch')
            ->withCount('assessmentRecords')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $options = new class
        {
            const SPECIES = ['Cat', 'Dog'];

            const AGE_GROUPS = ['Baby', 'Young', 'Adult', 'Senior'];

            const HEALTH_STATUSES = ['Healthy', 'Needs Vet', 'Under Treatment', 'Critical'];

            const ADOPTION_STATUSES = ['Available', 'Soft-Reserved', 'Adopted', 'Under Review', 'On Hold', 'Assessing'];
        };

        return view('admin.animal.index', compact('pets', 'options'));
    }
}
