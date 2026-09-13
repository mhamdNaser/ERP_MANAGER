<?php
namespace App\Modules\Communications\Requests;

use App\Modules\Communications\Services\FormalPartyResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFormalCorrespondenceRequest extends FormRequest
{
    public function authorize(): bool { return (bool) $this->user(); }

    protected function prepareForValidation(): void
    {
        foreach (['parent_id', 'source_id', 'target_id'] as $key) {
            if ($this->input($key) === '') {
                $this->merge([$key => null]);
            }
        }
    }

    public function rules(): array
    {
        $partyTypes = ['our_org', 'external_entity', 'branch', 'department', 'office', 'general_manager', 'diwan'];

        return [
            'direction' => ['required', Rule::in(array_keys(FormalPartyResolver::DIRECTION_RULES))],
            'parent_id' => ['nullable', 'integer', 'exists:formal_correspondences,id'],
            'subject' => ['required', 'string', 'max:180'],
            'summary' => ['nullable', 'string', 'max:2000'],
            'body' => ['nullable', 'string'],
            'source_type' => ['required', Rule::in($partyTypes)],
            'source_id' => ['nullable', 'integer'],
            'source_name' => ['nullable', 'string', 'max:180'],
            'target_type' => ['required', Rule::in($partyTypes)],
            'target_id' => ['nullable', 'integer'],
            'target_name' => ['nullable', 'string', 'max:180'],
            'first_place' => ['nullable', 'string', 'max:180'],
            'first_note' => ['nullable', 'string', 'max:1000'],
            'issued_at' => ['nullable', 'date'],
            'attachment' => ['nullable', 'file', 'max:20480', 'mimes:pdf,doc,docx,png,jpg,jpeg'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $direction = $this->input('direction');
            $rules = FormalPartyResolver::DIRECTION_RULES[$direction] ?? null;
            if (! $rules) return;

            if (! in_array($this->input('source_type'), $rules['source'], true)) {
                $validator->errors()->add('source_type', 'الجهة المصدرة لا تناسب نوع المراسلة المختار.');
            }

            if (! in_array($this->input('target_type'), $rules['target'], true)) {
                $validator->errors()->add('target_type', 'الجهة المخاطبة لا تناسب نوع المراسلة المختار.');
            }

            foreach (['source', 'target'] as $side) {
                $type = $this->input("{$side}_type");
                if ($type === 'external_entity' && ! $this->input("{$side}_id") && ! trim((string) $this->input("{$side}_name"))) {
                    $validator->errors()->add("{$side}_name", 'يجب تحديد اسم الجهة الخارجية.');
                }
                if (in_array($type, ['branch', 'department', 'office'], true) && ! $this->input("{$side}_id")) {
                    $validator->errors()->add("{$side}_id", 'يجب تحديد الجهة الداخلية.');
                }
            }
        });
    }
}
