<?php

namespace App\Http\Controllers;

use App\Enums\AvailabilityStatus;
use App\Models\Branch;
use App\Models\Pet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PetController extends Controller
{
    // ─── Public Catalog ──────────────────────────────────────────────────────

    /**
     * GET /pets — public catalog (excludes archived via global scope)
     */
    public function index(Request $request)
    {
        $query = Pet::with('branch');

        if ($request->filled('species')) {
            $query->where('species', $request->species);
        }
        if ($request->filled('status')) {
            $query->where('availability_status', $request->status);
        }

        $pets = $query->paginate(12);

        return view('pets.index', compact('pets'));
    }

    /**
     * GET /pets/{pet} — single pet detail (route-model binding)
     */
    public function show(Pet $pet)
    {
        $pet->load('branch');
        return view('pets.show', compact('pet'));
    }

    // ─── Staff Management ─────────────────────────────────────────────────────

    /**
     * GET /pets/create — render create form (staff only)
     */
    public function create()
    {
        $branches = Branch::all();
        return view('pets.create', compact('branches'));
    }

    /**
     * POST /pets — store new pet (staff only)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'              => 'required|string|max:255',
            'species'           => 'required|in:Cat,Dog',
            'breed'             => 'nullable|string|max:255',
            'age'               => 'nullable|integer|min:0',
            'sex'               => 'nullable|in:Male,Female',
            'health_status'     => 'nullable|string|max:255',
            'behavioral_notes'  => 'nullable|string',
            'branch_id'         => 'nullable|exists:branches,id',
            'intake_date'       => 'nullable|date',
            'photo'             => 'nullable|image|mimes:jpg,jpeg,png,webp|max:40960',
        ]);

        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('pets', 'public');
        }

        Pet::create([
            'name'                => $validated['name'],
            'species'             => $validated['species'],
            'breed'               => $validated['breed'] ?? null,
            'age'                 => $validated['age'] ?? null,
            'sex'                 => $validated['sex'] ?? null,
            'health_status'       => $validated['health_status'] ?? null,
            'behavioral_notes'    => $validated['behavioral_notes'] ?? null,
            'availability_status' => AvailabilityStatus::Available->value,
            'branch_id'           => $validated['branch_id'] ?? null,
            'intake_date'         => $validated['intake_date'] ?? now()->toDateString(),
            'photo_path'          => $photoPath,
            'is_archived'         => false,
            'version'             => 1,
        ]);

        return redirect()->route('admin.animals.index')
            ->with('success', 'Pet added successfully.');
    }

    /**
     * GET /pets/{pet}/edit — render edit form (staff only)
     */
    public function edit(Pet $pet)
    {
        $branches = Branch::all();
        return view('pets.edit', compact('pet', 'branches'));
    }

    /**
     * PUT /pets/{pet} — update pet with optimistic concurrency (staff only)
     */
    public function update(Request $request, Pet $pet)
    {
        $validated = $request->validate([
            'name'              => 'required|string|max:255',
            'species'           => 'required|in:Cat,Dog',
            'breed'             => 'nullable|string|max:255',
            'age'               => 'nullable|integer|min:0',
            'sex'               => 'nullable|in:Male,Female',
            'health_status'     => 'nullable|string|max:255',
            'behavioral_notes'  => 'nullable|string',
            'branch_id'         => 'nullable|exists:branches,id',
            'photo'             => 'nullable|image|mimes:jpg,jpeg,png,webp|max:40960',
            'version'           => 'required|integer',
        ]);

        $submittedVersion = (int) $validated['version'];

        // Optimistic concurrency check
        $updated = Pet::withoutGlobalScope('notArchived')
            ->where('id', $pet->id)
            ->where('version', $submittedVersion)
            ->update(array_filter([
                'name'             => $validated['name'],
                'species'          => $validated['species'],
                'breed'            => $validated['breed'],
                'age'              => $validated['age'],
                'sex'              => $validated['sex'],
                'health_status'    => $validated['health_status'],
                'behavioral_notes' => $validated['behavioral_notes'],
                'branch_id'        => $validated['branch_id'],
                'photo_path'       => $this->handlePhotoUpload($request, $pet->photo_path),
                'version'          => $submittedVersion + 1,
            ], fn($v) => $v !== null));

        if ($updated === 0) {
            return back()->withErrors([
                'concurrency' => 'This record was changed by someone else while you were editing it. Please reload and try again.',
            ])->withInput();
        }

        return redirect()->route('admin.animals.index')
            ->with('success', 'Pet updated successfully.');
    }

    /**
     * POST /pets/{pet}/archive — soft-delete (admin only)
     */
    public function archive(Pet $pet)
    {
        Pet::withoutGlobalScope('notArchived')
            ->where('id', $pet->id)
            ->update(['is_archived' => true]);

        return redirect()->route('admin.animals.index')
            ->with('success', 'Pet archived successfully.');
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    private function handlePhotoUpload(Request $request, ?string $existingPath): string
    {
        if ($request->hasFile('photo')) {
            return $request->file('photo')->store('pets', 'public');
        }
        return $existingPath ?? '';
    }
}
