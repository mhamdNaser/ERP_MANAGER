<?php
namespace App\Modules\Organization\Requests;
use Illuminate\Foundation\Http\FormRequest;

class StoreBranchRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->can('branches.create') ?? false; }
    public function rules(): array { return ['name' => ['required','max:255'], 'code' => ['required','max:50','unique:branches'], 'description' => ['nullable','string'], 'is_active' => ['sometimes','boolean']]; }
}
