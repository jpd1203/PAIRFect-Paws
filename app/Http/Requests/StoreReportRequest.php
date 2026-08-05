<?php

namespace App\Http\Requests;

use App\Support\ReportOptions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'check_in_id' => ['required', 'integer', 'exists:check_ins,id'],
            'pet_id' => ['required', 'integer', 'exists:pets,id'],
            'milestone' => ['required', 'string'],
            'health_status' => ['required', Rule::in(ReportOptions::HEALTH_STATUSES)],
            'eating_and_drinking' => ['required', Rule::in(ReportOptions::EATING_HABITS)],
            'behavior' => ['required', Rule::in(ReportOptions::BEHAVIORS)],
            'living_conditions' => ['required', Rule::in(ReportOptions::LIVING_CONDITIONS)],
            'vet_visit' => ['required', 'boolean'],
            'concerns' => ['nullable', 'string', 'max:2000'],
            'photo' => ['nullable', 'file', 'image', 'max:5120'],
        ];
    }
}
