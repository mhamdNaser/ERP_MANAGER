<?php
namespace App\Modules\Permissions\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SyncRolePermissionsRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->can('roles.permissions.manage') ?? false; }
    public function rules(): array
    {
        return ['permissions' => ['present', 'array'], 'permissions.*' => ['string', 'exists:permissions,name']];
    }
}
