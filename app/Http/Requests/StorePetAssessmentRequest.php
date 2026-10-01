<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePetAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isStaff() ?? false;
    }

    public function rules(): array
    {
        $species = strtolower($this->route('pet')->species->value);
        $groups = config("matching.items.{$species}");
        $rules = [
            'responses' => ['required', 'array:'.implode(',', array_keys($groups))],
        ];
        foreach ($groups as $group => $items) {
            $rules["responses.{$group}"] = ['sometimes', 'array:'.implode(',', array_keys($items))];
            foreach ($items as $key => $prompt) {
                $rules["responses.{$group}.{$key}"] = ['nullable', 'integer', 'between:0,4'];
            }
        }

        return $rules;
    }
}
