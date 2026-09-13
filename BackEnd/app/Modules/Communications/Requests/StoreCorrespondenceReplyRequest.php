<?php
namespace App\Modules\Communications\Requests;
use Illuminate\Foundation\Http\FormRequest;

class StoreCorrespondenceReplyRequest extends FormRequest {
    public function authorize(): bool { return $this->user()?->can('messages.reply')??false; }
    public function rules(): array { return ['content'=>['required','string'],'attachment'=>['nullable','file','max:10240','mimes:pdf,doc,docx,png,jpg,jpeg']]; }
}
