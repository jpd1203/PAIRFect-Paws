<?php

namespace App\Http\Controllers;

use App\Models\Pet;
use Illuminate\Http\Request;

class AnimalController extends Controller
{
    public function index(Request $request)
    {
        $species = $request->query('species', 'All Species');
        $age = $request->query('age', 'All Ages');

        $pets = Pet::query()
            ->species($species)
            ->ageGroup($age)
            ->where('status', 'Available')
            ->orderBy('name')
            ->get();

        // AJAX filter requests only need the grid partial re-rendered
        if ($request->ajax() || $request->wantsJson()) {
            return view('animal._pet-grid', ['pets' => $pets]);
        }

        return view('animal.index', [
            'pets' => $pets,
            'speciesFilter' => $species,
            'ageFilter' => $age,
        ]);
    }

    /**
     * Modal content fetched via AJAX from the browse-pets grid.
     * Read-only lookup — no ownership check needed since pet listings are public.
     */
    public function show(Pet $pet)
    {
        return view('animal._pet-modal-content', ['pet' => $pet]);
    }
}
