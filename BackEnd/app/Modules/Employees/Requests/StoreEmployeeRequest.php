<?php
namespace App\Modules\Employees\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->can('employees.create') ?? false; }
    public function rules(): array
    {
        return array_merge(['name' => ['required'], 'email' => ['required', 'email', 'unique:users'], 'password' => ['required', 'min:8'], 'role' => ['required', 'exists:roles,name'], 'job_title' => ['nullable'], 'employee_number' => ['nullable', 'unique:users'], 'employment_type' => ['sometimes', 'in:fixed,contract'], 'branch_id' => ['nullable', 'exists:branches,id'], 'department_id' => ['nullable', 'exists:departments,id'], 'office_id' => ['nullable', 'exists:offices,id'], 'is_communication_officer' => ['sometimes', 'boolean']], $this->optionalDetailsRules());
    }

    private function optionalDetailsRules(): array { return ['address'=>['nullable','array'],'family_details'=>['nullable','array'],'personal_details'=>['nullable','array']]; }
}
