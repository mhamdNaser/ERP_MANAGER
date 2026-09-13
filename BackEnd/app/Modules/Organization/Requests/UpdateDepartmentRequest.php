<?php
namespace App\Modules\Organization\Requests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDepartmentRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->can('departments.update') ?? false; }
    public function rules(): array { return ['branch_id' => ['sometimes','nullable','exists:branches,id'], 'name' => ['sometimes','max:255'], 'code' => ['sometimes','max:50',Rule::unique('departments')->ignore($this->route('department'))], 'description' => ['nullable','string'], 'is_active' => ['sometimes','boolean']]; }
}
