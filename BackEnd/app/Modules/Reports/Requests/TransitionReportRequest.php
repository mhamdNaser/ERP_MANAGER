<?php
namespace App\Modules\Reports\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TransitionReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->can('reports.transition') ?? false)
            || $this->user()?->primaryRole() === 'database_manager';
    }
    public function rules(): array { return ['action' => ['required', 'in:submit,forward_branch,forward_general,return,approve'], 'note' => ['required_if:action,return', 'nullable', 'max:2000']]; }
}
