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
            'group_id' => ['required', 'integer', 'exists:groups,id'],
            'service_option_id' => [
                'required',
                'integer',
                'exists:service_options,id',
            ],
            'file' => 'required|file|max:10240'
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'File wajib dipilih.',
            'file.file' => 'File tidak valid.',
            'file.max' => 'Ukuran file maksimal 10 MB.',
            'group_id.required' => 'Group tujuan wajib dipilih.',
            'group_id.exists' => 'Group tujuan tidak valid.',
            'service_option_id.required' => 'Opsi layanan wajib dipilih.',
            'service_option_id.exists' => 'Opsi layanan tidak valid.',
        ];
    }
}
