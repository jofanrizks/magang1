<?php

namespace App\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

class UploadGroupFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => 'required|file|max:10240'
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'File wajib dipilih.',
            'file.file' => 'File tidak valid.',
            'file.max' => 'Ukuran file maksimal 10 MB.',
        ];
    }
}