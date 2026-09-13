<?php
namespace App\Modules\Organization\Requests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBranchRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->can('branches.update') ?? false; }
    public function rules(): array { return ['name' => ['sometimes','max:255'], 'code' => ['sometimes','max:50',Rule::unique('branches')->ignore($this->route('branch'))], 'description' => ['nullable','string'], 'is_active' => ['sometimes','boolean']]; }
}
