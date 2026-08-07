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
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('admin.animal.index', compact('pets'));
    }
}
