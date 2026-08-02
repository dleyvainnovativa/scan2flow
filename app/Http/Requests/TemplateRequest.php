<?php

namespace App\Http\Requests;

use App\Models\TemplateField;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'area_id'           => ['required', 'exists:areas,id'],
            'name'              => ['required', 'string', 'max:120'],
            'description'       => ['nullable', 'string', 'max:500'],
            'input_folder_path' => ['nullable', 'string', 'max:255'],
            'naming_rule'       => ['required', Rule::in(['same_name'])],

            // 1 to 5 metadata fields.
            'fields'                => ['required', 'array', 'min:1', 'max:5'],
            'fields.*.label'        => ['required', 'string', 'max:80'],
            'fields.*.type'         => ['required', Rule::in(TemplateField::TYPES)],
            'fields.*.is_required'  => ['nullable', 'boolean'],
            // options only meaningful for selects; validated as array of strings.
            'fields.*.options'      => ['nullable', 'array'],
            'fields.*.options.*'    => ['string', 'max:80'],

            'ai_enabled' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'fields.required' => 'Agrega al menos un metadato.',
            'fields.max'      => 'Máximo 5 metadatos por plantilla.',
            'fields.*.label.required' => 'Cada metadato necesita un nombre.',
        ];
    }
}
