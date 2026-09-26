<?php

namespace App\Http\Controllers;

use App\Enums\AvailabilityStatus;
use App\Models\AdoptionApplication;
use App\Models\Branch;
use App\Models\Pet;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

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

        $pets = $query->latest()->paginate(12);

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

    /**
     * GET /pets/{pet}/modal — fetch pet modal content (AJAX)
     */
    public function modal(Pet $pet)
    {
        $pet->load('branch');

        return view('animal._pet-modal-content', compact('pet'));
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
            'name' => 'required|string|max:255',
            'species' => 'required|in:Cat,Dog',
            'breed' => 'nullable|string|max:255',
            'age_years' => 'nullable|integer|min:0',
            'age_months' => 'nullable|integer|min:0|max:11',
            'sex' => 'nullable|in:Male,Female',
            'health_status' => 'nullable|string|max:255',
            'behavioral_notes' => 'nullable|string',
            'status' => 'required|string|max:255',
            'branch_id' => 'nullable|exists:branches,id',
            'physical_size' => 'nullable|string|max:255',
            'medical_needs' => 'nullable|numeric|min:1|max:5',
            'vaccination_record_status' => 'nullable|string|max:255',
            'intake_date' => 'nullable|date|before_or_equal:today',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:102400',
        ]);

        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('pets', 'public');
        }

        $age = ((int) ($validated['age_years'] ?? 0)) * 12 + ((int) ($validated['age_months'] ?? 0));

        $pet = Pet::create([
            'name' => $validated['name'],
            'species' => $validated['species'],
            'breed' => $validated['breed'] ?? null,
            'age' => $age > 0 ? $age : null,
            'sex' => $validated['sex'] ?? null,
            'health_status' => $validated['health_status'] ?? null,
            'behavioral_notes' => $validated['behavioral_notes'] ?? null,
            'availability_status' => $validated['status'],
            'branch_id' => $validated['branch_id'] ?? null,
            'physical_size' => $validated['physical_size'] ?? null,
            'medical_needs' => $validated['medical_needs'] ?? null,
            'vaccination_record_status' => $validated['vaccination_record_status'] ?? null,
            'intake_date' => $validated['intake_date'] ?? now()->toDateString(),
            'photo_path' => $photoPath,
            'is_archived' => false,
            'version' => 1,
        ]);

        AuditLogService::log(
            auth()->id(),
            'Added New Animal',
            'Pet',
            $pet->id,
            "Added animal: {$pet->name} ({$pet->species->value})"
        );

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
            'name' => 'required|string|max:255',
            'species' => 'required|in:Cat,Dog',
            'breed' => 'nullable|string|max:255',
            'age_years' => 'nullable|integer|min:0',
            'age_months' => 'nullable|integer|min:0|max:11',
            'sex' => 'nullable|in:Male,Female',
            'health_status' => 'nullable|string|max:255',
            'behavioral_notes' => 'nullable|string',
            'status' => 'required|string|max:255',
            'branch_id' => 'nullable|exists:branches,id',
            'physical_size' => 'nullable|string|max:255',
            'medical_needs' => 'nullable|numeric|min:1|max:5',
            'vaccination_record_status' => 'nullable|string|max:255',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:102400',
            'version' => 'required|integer',
        ]);

        $submittedVersion = (int) $validated['version'];
        $age = ((int) ($validated['age_years'] ?? 0)) * 12 + ((int) ($validated['age_months'] ?? 0));

        $hasActivePrimary = AdoptionApplication::where('pet_id', $pet->id)
            ->where('is_primary_candidate', true)
            ->exists();
        if ($hasActivePrimary && $validated['status'] !== AvailabilityStatus::SoftReserved->value) {
            return back()->withErrors([
                'status' => 'This pet has an active primary candidate and must remain Soft-Reserved. Manage the application queue instead.',
            ])->withInput();
        }
        if (! $hasActivePrimary && $validated['status'] === AvailabilityStatus::SoftReserved->value) {
            return back()->withErrors([
                'status' => 'Soft-Reserved is controlled automatically by the application queue.',
            ])->withInput();
        }

        // Optimistic concurrency check
        $updated = Pet::withoutGlobalScope('notArchived')
            ->where('id', $pet->id)
            ->where('version', $submittedVersion)
            ->update(array_filter([
                'name' => $validated['name'],
                'species' => $validated['species'],
                'breed' => $validated['breed'] ?? null,
                'age' => $age > 0 ? $age : null,
                'sex' => $validated['sex'] ?? null,
                'health_status' => $validated['health_status'] ?? null,
                'behavioral_notes' => $validated['behavioral_notes'] ?? null,
                'availability_status' => $validated['status'],
                'branch_id' => $validated['branch_id'] ?? null,
                'physical_size' => $validated['physical_size'] ?? null,
                'medical_needs' => $validated['medical_needs'] ?? null,
                'vaccination_record_status' => $validated['vaccination_record_status'] ?? null,
                'photo_path' => $this->handlePhotoUpload($request, $pet->photo_path),
                'version' => $submittedVersion + 1,
            ], fn ($v) => $v !== null));

        if ($updated === 0) {
            return back()->withErrors([
                'concurrency' => 'This record was changed by someone else while you were editing it. Please reload and try again.',
            ])->withInput();
        }

        AuditLogService::log(
            auth()->id(),
            'Updated Animal Profile',
            'Pet',
            $pet->id,
            "Updated animal: {$pet->name}"
        );

        return redirect()->route('admin.animals.index')
            ->with('success', 'Pet updated successfully.');
    }

    /**
     * POST /pets/{pet}/archive — soft-delete (admin only)
     */
    public function archive(Pet $pet)
    {
        Gate::authorize('archive', $pet);

        if (AdoptionApplication::where('pet_id', $pet->id)->where('is_primary_candidate', true)->exists()) {
            return back()->withErrors([
                'pet' => 'This pet cannot be archived while its reservation queue has an active primary candidate.',
            ]);
        }

        Pet::withoutGlobalScope('notArchived')
            ->where('id', $pet->id)
            ->update(['is_archived' => true]);

        AuditLogService::log(
            auth()->id(),
            'Archived Animal Profile',
            'Pet',
            $pet->id,
            "Archived animal: {$pet->name}"
        );

        return back()->with('success', 'Pet archived successfully.');
    }

    /**
     * DELETE /pets/{pet} — delete pet and log to audit logs
     */
    public function restore(Pet $pet)
    {
        Gate::authorize('restore', $pet);

        Pet::withoutGlobalScope('notArchived')
            ->where('id', $pet->id)
            ->update(['is_archived' => false]);

        AuditLogService::log(
            auth()->id(),
            'Restored Animal Profile',
            'Pet',
            $pet->id,
            "Restored animal: {$pet->name}"
        );

        return back()->with('success', 'Pet restored successfully.');
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

