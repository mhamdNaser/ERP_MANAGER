<?php
namespace App\Modules\Communications\Requests;
use Illuminate\Foundation\Http\FormRequest;

class StoreCircularRequest extends FormRequest {
    public function authorize(): bool { return $this->user()?->can('circulars.create')??false; }
    public function rules(): array { return ['title'=>['required','max:255'],'content'=>['required','string'],'audience'=>['required','in:department_all,department_fixed_employees,department_contract_employees,branch_heads,branch_all,branch_fixed_employees,branch_contract_employees,general_branch_managers,general_management,general_all,general_fixed_employees,general_contract_employees'],'attachment'=>['nullable','file','max:10240','mimes:pdf,doc,docx,png,jpg,jpeg']]; }
}
