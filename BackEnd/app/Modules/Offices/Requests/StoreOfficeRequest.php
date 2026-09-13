<?php
namespace App\Modules\Offices\Requests;
use Illuminate\Foundation\Http\FormRequest;

class StoreOfficeRequest extends FormRequest {
    public function authorize(): bool { return $this->user()?->can('offices.manage')??false; }
    public function rules(): array { return ['name'=>['required','max:255'],'code'=>['required','max:50','unique:offices,code'],'description'=>['nullable','string'],'is_active'=>['sometimes','boolean']]; }
}
