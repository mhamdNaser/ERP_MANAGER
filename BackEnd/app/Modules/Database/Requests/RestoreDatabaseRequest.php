<?php

namespace App\Modules\Database\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RestoreDatabaseRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'password' => ['required', 'string'],
            'file_name' => ['required', 'string', 'regex:/^[A-Za-z0-9._-]+$/'],
            'tables' => ['sometimes', 'array', 'min:1'],
            'tables.*' => ['string'],
            'restore_files' => ['sometimes', 'boolean'],
        ];
    }
}
