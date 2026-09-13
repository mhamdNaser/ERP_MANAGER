<?php
namespace App\Modules\Communications\Requests;
use Illuminate\Foundation\Http\FormRequest;

class StoreCorrespondenceRequest extends FormRequest {
    public function authorize(): bool { return $this->user()?->can('messages.create')??false; }
    protected function prepareForValidation(): void
    {
        if ($this->has('allow_reply')) {
            $this->merge([
                'allow_reply' => filter_var($this->input('allow_reply'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
            ]);
        }
    }
    public function rules(): array { return ['recipient_id'=>['required','exists:users,id'],'subject'=>['required','max:255'],'content'=>['required','string'],'purpose'=>['required','string'],'allow_reply'=>['required','boolean'],'attachment'=>['nullable','file','max:10240','mimes:pdf,doc,docx,png,jpg,jpeg']]; }
}
