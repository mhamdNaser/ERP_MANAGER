<?php

namespace App\Modules\Database\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateBackupRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'format' => ['sometimes', 'string', 'in:json,sql,backup'],
            'tables' => ['sometimes', 'array'],
            'tables.*' => ['string'],
            'bundle_files' => ['sometimes', 'boolean'],
            // حزمة جاهزة: تُغني عن اختيار الجداول يدوياً وتحدّدها بنفسها.
            'preset' => ['sometimes', 'string', 'max:40'],
        ];
    }
}
