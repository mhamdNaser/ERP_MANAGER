<?php
namespace App\Modules\Communications\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMessageReplyRequest extends FormRequest {
    public function authorize(): bool { return ($this->user()?->can('messages.reply') || $this->user()?->can('correspondences.reply')) ?? false; }
    public function rules(): array { return ['content'=>['required','max:5000'],'attachment'=>['nullable','file','max:10240','mimes:pdf,doc,docx,png,jpg,jpeg']]; }
}
