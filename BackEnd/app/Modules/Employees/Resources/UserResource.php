<?php
namespace App\Modules\Employees\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $role = $this->getRoleNames()->first() ?? $this->role;

        return [
            'id' => $this->id, 'name' => $this->name, 'email' => $this->email,
            'role' => $role, 'roles' => $this->getRoleNames(),
            'permissions' => $this->getAllPermissions()->pluck('name'),
            'direct_permissions' => $this->getDirectPermissions()->pluck('name'), 'job_title' => $this->job_title,
            'employee_number' => $this->employee_number, 'employment_type' => $this->employment_type, 'branch_id' => $this->branch_id, 'department_id' => $this->department_id, 'office_id' => $this->office_id,
            'branch' => $this->whenLoaded('branch'), 'department' => $this->whenLoaded('department'), 'office' => $this->whenLoaded('office'), 'is_active' => $this->is_active,
            'digital_signature_path' => $this->digital_signature_path,
            'digital_signature_url' => $this->digital_signature_path ? Storage::disk('public')->url($this->digital_signature_path) : null,
            'can_manage_digital_signature' => in_array($role, ['department_head', 'branch_manager', 'general_manager'], true) || filled($this->office_id),
            'address' => $this->whenLoaded('address'), 'family_details' => $this->whenLoaded('familyDetails'),
            'personal_details' => $this->whenLoaded('personalDetails'),
        ];
    }
}
