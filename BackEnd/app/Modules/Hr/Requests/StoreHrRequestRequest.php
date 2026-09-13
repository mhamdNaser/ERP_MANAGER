<?php

namespace App\Modules\Hr\Requests;

use App\Models\HrRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHrRequestRequest extends FormRequest
{
    public function authorize(): bool { return (bool) $this->user(); }

    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'type' => ['required', Rule::in(HrRequest::TYPES)],
            'subtype' => ['nullable', 'string', 'max:40'],
            'start_date' => ['nullable', 'date', 'required_if:type,leave,mission'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date', 'required_if:type,leave,mission'],
            'start_time' => ['nullable', 'date_format:H:i', 'required_if:type,departure'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time', 'required_if:type,departure'],
            'days' => ['nullable', 'numeric', 'min:0', 'max:365'],
            'hours' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'destination' => ['nullable', 'string', 'max:180', 'required_if:type,mission'],
            'reason' => ['required', 'string', 'max:2000'],
            'attachment' => ['nullable', 'file', 'max:20480', 'mimes:pdf,doc,docx,png,jpg,jpeg'],
        ];
    }

    /** الإجازة بالأيام والمغادرة بالساعات — تُحسب المدة هنا لا في الواجهة. */
    protected function passedValidation(): void
    {
        if ($this->input('type') === 'departure' && $this->filled(['start_time', 'end_time'])) {
            $start = \Carbon\Carbon::createFromFormat('H:i', $this->input('start_time'));
            $end = \Carbon\Carbon::createFromFormat('H:i', $this->input('end_time'));
            $this->merge(['hours' => round($start->diffInMinutes($end) / 60, 1)]);
        }
    }
}
