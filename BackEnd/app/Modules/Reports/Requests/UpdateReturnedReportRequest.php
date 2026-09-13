<?php
namespace App\Modules\Reports\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReturnedReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->can('reports.update.returned') ?? false)
            || $this->user()?->primaryRole() === 'database_manager';
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'in:daily_report,daily_plan,weekly_report,weekly_plan'],
            'period_start' => ['required', 'date'], 'period_end' => ['nullable', 'date'],
            'title' => ['required', 'max:255'], 'summary' => ['required', 'string'],
            'achievements' => ['nullable', 'string'], 'challenges' => ['nullable', 'string'], 'next_steps' => ['nullable', 'string'],
        ];
    }
}
