<?php
namespace App\Modules\Employees\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->can('employees.update') ?? false; }
    public function rules(): array
    {
        return ['name' => ['sometimes'], 'email' => ['sometimes', 'email'], 'role' => ['sometimes', 'exists:roles,name'], 'job_title' => ['nullable'], 'employment_type' => ['sometimes', 'in:fixed,contract'], 'branch_id' => ['nullable', 'exists:branches,id'], 'department_id' => ['nullable', 'exists:departments,id'], 'office_id' => ['nullable', 'exists:offices,id'], 'is_active' => ['sometimes', 'boolean'], 'address'=>['nullable','array'],'family_details'=>['nullable','array'],'personal_details'=>['nullable','array']];
    }
}
