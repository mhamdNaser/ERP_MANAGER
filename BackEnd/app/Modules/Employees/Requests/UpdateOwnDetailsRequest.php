<?php
namespace App\Modules\Employees\Requests;
use Illuminate\Foundation\Http\FormRequest;

class UpdateOwnDetailsRequest extends FormRequest {
    public function authorize(): bool { return (bool)$this->user(); }
    public function rules(): array { return [
        'address'=>['nullable','array'],'address.country'=>['nullable','string'],'address.city'=>['nullable','string'],'address.district'=>['nullable','string'],'address.street'=>['nullable','string'],'address.building'=>['nullable','string'],'address.details'=>['nullable','string'],
        'family_details'=>['nullable','array'],'family_details.marital_status'=>['nullable','string'],'family_details.spouse_name'=>['nullable','string'],'family_details.children_count'=>['nullable','integer','min:0'],'family_details.emergency_contact_name'=>['nullable','string'],'family_details.emergency_contact_phone'=>['nullable','string'],'family_details.emergency_contact_relation'=>['nullable','string'],
        'personal_details'=>['nullable','array'],'personal_details.birth_date'=>['nullable','date'],'personal_details.national_id'=>['nullable','string'],'personal_details.gender'=>['nullable','string'],'personal_details.height_cm'=>['nullable','numeric'],'personal_details.weight_kg'=>['nullable','numeric'],'personal_details.blood_type'=>['nullable','string'],'personal_details.shoe_size'=>['nullable','string'],'personal_details.trouser_size'=>['nullable','string'],'personal_details.shirt_size'=>['nullable','string'],'personal_details.jacket_size'=>['nullable','string'],'personal_details.uniform_notes'=>['nullable','string'],
    ]; }
}
