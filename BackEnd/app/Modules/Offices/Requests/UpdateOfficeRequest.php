<?php
namespace App\Modules\Offices\Requests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOfficeRequest extends FormRequest {
    public function authorize(): bool { return $this->user()?->can('offices.manage')??false; }
    public function rules(): array { return ['name'=>['sometimes','max:255'],'code'=>['sometimes','max:50',Rule::unique('offices')->ignore($this->office)],'description'=>['nullable','string'],'is_active'=>['sometimes','boolean']]; }
}
