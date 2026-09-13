<?php
namespace App\Modules\Organization\Requests;
use Illuminate\Foundation\Http\FormRequest;

class StoreDepartmentRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->can('departments.create') ?? false; }
    public function rules(): array { return ['branch_id' => ['nullable','exists:branches,id'], 'name' => ['required','max:255'], 'code' => ['required','max:50','unique:departments'], 'description' => ['nullable','string'], 'is_active' => ['sometimes','boolean']]; }
}
