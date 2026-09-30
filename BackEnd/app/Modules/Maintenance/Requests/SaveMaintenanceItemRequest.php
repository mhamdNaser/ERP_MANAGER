<?php

namespace App\Modules\Maintenance\Requests;

use App\Modules\Maintenance\Services\MaintenanceWorkflow;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveMaintenanceItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'part_number' => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', 'exists:maintenance_categories,id'],
            'type_id' => [
                'nullable', 'integer',
                // النوع يتبع فئته، فلا يُقبل نوع من فئة أخرى.
                Rule::exists('maintenance_types', 'id')->where('category_id', $this->input('category_id') ?: 0),
            ],
            'brand_id' => ['nullable', 'integer', 'exists:maintenance_brands,id'],
            'device' => ['nullable', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:50'],
            'unit_price' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'min_quantity' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'location' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'image' => ['nullable', 'image', 'max:5120'],
            'remove_image' => ['nullable', 'boolean'],
            'initial_quantity' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'initial_status' => ['nullable', Rule::in(MaintenanceWorkflow::STATUSES)],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'اسم القطعة مطلوب.',
            'type_id.exists' => 'النوع المختار لا يتبع الفئة المختارة.',
            'image.image' => 'الصورة يجب أن تكون ملف صورة (jpg أو png).',
        ];
    }
}
