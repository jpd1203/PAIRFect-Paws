<?php

namespace App\Http\Controllers;

use App\Enums\AvailabilityStatus;
use App\Models\AdoptionApplication;
use App\Models\AdopterProfile;
use App\Models\Branch;
use App\Models\Pet;
use App\Services\AuditLogService;
use App\Services\KnnRecommendationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class PetController extends Controller
{
    // ─── Public Catalog ──────────────────────────────────────────────────────

    /**
     * GET /pets — public catalog (excludes archived via global scope)
     */
    public function index(Request $request)
    {
        $query = Pet::fullyAssessed()->with('branch')
            ->where('availability_status', AvailabilityStatus::Available);

        if ($request->filled('species') && $request->species !== 'all' && $request->species !== 'All Species') {
            $query->where('species', $request->species);
        }

        if ($request->filled('age') && $request->age !== 'all' && $request->age !== 'All Ages') {
            $age = $request->age;
            if ($age === 'Young') {
                $query->where('age', '<=', 24);
            } elseif ($age === 'Adult') {
                $query->whereBetween('age', [25, 84]);
            } elseif ($age === 'Senior') {
                $query->where('age', '>=', 85);
            }
        }

        $pets = $query->latest('id')->paginate(16)->withQueryString();

        return view('pets.index', [
            'pets' => $pets,
            'speciesFilter' => $request->species ?? 'all',
            'ageFilter' => $request->age ?? 'all',
        ]);
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
    public function modal(Request $request, Pet $pet, KnnRecommendationService $matcher)
    {
        $pet->load('branch');
        $profile = $request->user()?->isAdopter() ? $request->user()->adopterProfile : null;
        if (! $request->user() && $request->session()->has('guest_adopter_profile')) {
            $profile = (new AdopterProfile)->forceFill($request->session()->get('guest_adopter_profile'));
        }
        $match = $profile ? $matcher->calculateMatch($profile, $pet) : null;

        return view('animal._pet-modal-content', compact('pet', 'match'));
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
            'description' => 'nullable|string',
            'status' => ['required', Rule::enum(AvailabilityStatus::class)],
            'branch_id' => 'nullable|exists:branches,id',
            'physical_size' => ['nullable', Rule::in(array_keys(config('matching.size_levels')))],
            'medical_needs' => 'nullable|numeric|min:1|max:5',
            'life_stage' => ['nullable', Rule::in(['young', 'adult', 'senior'])],
            'has_aggression_history' => 'nullable|boolean',
            'high_vocalization' => 'nullable|boolean',
            'vaccination_record_status' => 'nullable|string|max:255',
            'intake_date' => 'nullable|date|before_or_equal:today',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:10240',
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
            'description' => $validated['description'] ?? null,
            'availability_status' => $validated['status'],
            'branch_id' => $validated['branch_id'] ?? null,
            'physical_size' => $validated['physical_size'] ?? null,
            'medical_needs' => $validated['medical_needs'] ?? null,
            'life_stage' => $validated['life_stage'] ?? null,
            'has_aggression_history' => $validated['has_aggression_history'] ?? null,
            'aggression_history_verified_at' => ($validated['has_aggression_history'] ?? null) === null ? null : now(),
            'high_vocalization' => $validated['high_vocalization'] ?? null,
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
            'description' => 'nullable|string',
            'status' => ['required', Rule::enum(AvailabilityStatus::class)],
            'branch_id' => 'nullable|exists:branches,id',
            'physical_size' => ['nullable', Rule::in(array_keys(config('matching.size_levels')))],
            'medical_needs' => 'nullable|numeric|min:1|max:5',
            'life_stage' => ['nullable', Rule::in(['young', 'adult', 'senior'])],
            'has_aggression_history' => 'nullable|boolean',
            'high_vocalization' => 'nullable|boolean',
            'vaccination_record_status' => 'nullable|string|max:255',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:10240',
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
        $changes = array_filter([
            'name' => $validated['name'],
            'species' => $validated['species'],
            'breed' => $validated['breed'] ?? null,
            'age' => $age > 0 ? $age : null,
            'sex' => $validated['sex'] ?? null,
            'health_status' => $validated['health_status'] ?? null,
            'behavioral_notes' => $validated['behavioral_notes'] ?? null,
            'description' => $validated['description'] ?? null,
            'availability_status' => $validated['status'],
            'branch_id' => $validated['branch_id'] ?? null,
            'physical_size' => $validated['physical_size'] ?? null,
            'medical_needs' => $validated['medical_needs'] ?? null,
            'vaccination_record_status' => $validated['vaccination_record_status'] ?? null,
            'photo_path' => $this->handlePhotoUpload($request, $pet->photo_path),
            'version' => $submittedVersion + 1,
        ], fn ($v) => $v !== null);

        // Explicit unknown selections clear canonical profile data; omitted fields retain it.
        // An unverified legacy aggression "No" remains stored but not trusted until confirmed.
        foreach (['physical_size', 'medical_needs', 'life_stage', 'high_vocalization'] as $field) {
            if (array_key_exists($field, $validated)) {
                $changes[$field] = $validated[$field];
            }
        }
        if (array_key_exists('has_aggression_history', $validated)) {
            if ($validated['has_aggression_history'] !== null) {
                $changes['has_aggression_history'] = $validated['has_aggression_history'];
                $changes['aggression_history_verified_at'] = now();
            } elseif ($pet->aggression_history_verified_at !== null || $pet->has_aggression_history === null) {
                $changes['has_aggression_history'] = null;
                $changes['aggression_history_verified_at'] = null;
            }
        }

        $updated = Pet::withoutGlobalScope('notArchived')
            ->where('id', $pet->id)
            ->where('version', $submittedVersion)
            ->update($changes);

        if ($updated === 0) {
            return back()->withErrors([
                'concurrency' => 'This record was changed by someone else while you were editing it. Please reload and try again.',
            ])->withInput();
        }

        app(\App\Services\Matching\ApplicationMatchService::class)->refreshPetSummary($pet->fresh());

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
