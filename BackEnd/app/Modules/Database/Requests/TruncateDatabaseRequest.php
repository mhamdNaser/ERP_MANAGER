<?php

namespace App\Modules\Database\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TruncateDatabaseRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'password' => ['required', 'string'],
            'tables' => ['required', 'array', 'min:1'],
            'tables.*' => ['string'],
        ];
    }
}
