<?php
namespace App\Modules\Communications\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFormalCorrespondenceEventRequest extends FormRequest
{
    public function authorize(): bool { return (bool) $this->user(); }

    public function rules(): array
    {
        return [
            'place' => ['nullable', 'string', 'max:180'],
            'target_type' => ['nullable', Rule::in(['diwan', 'general_manager', 'branch', 'department', 'office', 'external_entity', 'free_text'])],
            'target_id' => ['nullable', 'integer'],
            'action_required' => ['nullable', Rule::in(['route', 'decision', 'study', 'execution', 'reply', 'hold'])],
            'note' => ['nullable', 'string', 'max:1000'],
            'status' => ['nullable', 'string', 'max:80'],
            'date' => ['nullable', 'date'],
            'registry_number' => ['nullable', 'string', 'max:120'],
            'document_source' => ['nullable', 'string', 'max:180'],
            'document_target' => ['nullable', 'string', 'max:180'],
            'document_title' => ['required', 'string', 'max:180'],
            'document_body' => ['required', 'string'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:20480', 'mimes:pdf,doc,docx,png,jpg,jpeg'],
        ];
    }
}
