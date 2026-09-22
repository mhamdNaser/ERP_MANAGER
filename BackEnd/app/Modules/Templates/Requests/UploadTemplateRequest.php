<?php

namespace App\Modules\Templates\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadTemplateRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            // القوالب مستندات Word حصراً؛ صحّة بنيتها تُفحص في TemplateStorage.
            'template' => ['required', 'file', 'max:20480', 'mimes:docx'],
            'force' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'template.mimes' => 'القالب يجب أن يكون ملف Word بصيغة docx.',
            'template.max' => 'حجم القالب يتجاوز 20 ميغابايت.',
        ];
    }
}
