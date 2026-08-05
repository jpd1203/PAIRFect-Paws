<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Pet;
use App\Support\AdminOptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class AnimalController extends Controller
{
    public function index(Request $request)
    {
        $pets = Pet::query()
            ->species($request->query('species'))
            ->ageGroup($request->query('age'))
            ->healthStatus($request->query('health'))
            ->adoptionStatus($request->query('status'))
            ->orderByDesc('created_at')
            ->get();

        return view('admin.animal.index', [
            'pets' => $pets,
            'options' => AdminOptions::class,
        ]);
    }

    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'species' => ['required', Rule::in(AdminOptions::SPECIES)],
            'breed' => ['required', 'string', 'max:100'],
            'age_group' => ['required', Rule::in(AdminOptions::AGE_GROUPS)],
            'age_years' => ['nullable', 'integer', 'min:0', 'max:30'],
            'sex' => ['required', Rule::in(['Male', 'Female'])],
            'intake_date' => ['required', 'date'],
            'health_status' => ['required', Rule::in(AdminOptions::HEALTH_STATUSES)],
            'vaccination_records' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::in(AdminOptions::ADOPTION_STATUSES)],
            'physical_size' => ['required', Rule::in(AdminOptions::SIZES)],
            'notes' => ['nullable', 'string', 'max:2000'],
            'photo' => ['nullable', 'image', 'max:5120'],
        ];
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());

        if ($request->hasFile('photo')) {
            $data['image_path'] = $request->file('photo')->store('pets', 'public');
        }

        $pet = Pet::create($data);

        AuditLog::record(Auth::user(), "added new animal record for {$pet->name}");

        return back()->with('toast', ['type' => 'success', 'message' => "{$pet->name} added to Animal Records."]);
    }

    public function update(Request $request, Pet $animal)
    {
        $data = $request->validate($this->rules());

        if ($request->hasFile('photo')) {
            $data['image_path'] = $request->file('photo')->store('pets', 'public');
        }

        $animal->update($data);

        AuditLog::record(Auth::user(), "updated animal record for {$animal->name}");

        return back()->with('toast', ['type' => 'success', 'message' => "{$animal->name}'s record updated."]);
    }

    public function destroy(Pet $animal)
    {
        $name = $animal->name;
        $animal->delete();

        AuditLog::record(Auth::user(), "deleted animal record for {$name}");

        return back()->with('toast', ['type' => 'success', 'message' => "{$name} removed from Animal Records."]);
    }
}
