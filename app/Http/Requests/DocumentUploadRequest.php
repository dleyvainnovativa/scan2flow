<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DocumentUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // area edit permission checked in the controller
    }

    public function rules(): array
    {
        $maxKb = config('documents.max_upload_kb', 20480);

        return [
            'title'    => ['required', 'string', 'max:255'],
            'pdf'      => ['required', 'file', 'mimes:pdf', "max:{$maxKb}"],
            'xml'      => ['nullable', 'file', 'mimes:xml,txt', "max:{$maxKb}"],
            'metadata' => ['array'],
            'metadata.*' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'pdf.required' => 'Selecciona un archivo PDF.',
            'pdf.mimes'    => 'El archivo debe ser PDF.',
            'xml.mimes'    => 'El segundo archivo debe ser XML.',
        ];
    }
}
