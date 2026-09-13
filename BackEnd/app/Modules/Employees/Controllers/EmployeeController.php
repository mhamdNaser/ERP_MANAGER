<?php
namespace App\Modules\Employees\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\User;
use App\Modules\Employees\Repositories\Interfaces\EmployeeRepositoryInterface;
use App\Modules\Employees\Requests\StoreEmployeeRequest;
use App\Modules\Employees\Requests\UpdateEmployeeRequest;
use App\Modules\Employees\Requests\UpdateOwnDetailsRequest;
use App\Modules\Employees\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EmployeeController extends Controller
{
    public function __construct(private EmployeeRepositoryInterface $employees) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json(['employees' => UserResource::collection($this->employees->all($request->user()))]);
    }

    public function store(StoreEmployeeRequest $request): UserResource
    {
        return new UserResource($this->employees->create($request->user(), $request->validated()));
    }

    public function update(UpdateEmployeeRequest $request, User $employee): UserResource
    {
        return new UserResource($this->employees->update($request->user(), $employee, $request->validated()));
    }

    /** تعيين كلمة مرور جديدة للموظف دون الحاجة إلى القديمة. */
    public function updatePassword(Request $request, User $employee): JsonResponse
    {
        $data = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $this->employees->resetPassword($request->user(), $employee, $data['password']);

        return response()->json(['message' => __('messages.password_updated')]);
    }

    public function updatePermissions(Request $request, User $employee): UserResource
    {
        $data = $request->validate([
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        return new UserResource($this->employees->updatePermissions($request->user(), $employee, $data['permissions']));
    }

    public function destroy(Request $request, User $employee): JsonResponse
    {
        abort_if($employee->id === $request->user()->id, 422, __('messages.cannot_delete_self'));
        $this->employees->delete($request->user(), $employee);
        return response()->json(['message' => __('messages.employee_deleted')]);
    }

    public function profile(Request $request): UserResource
    {
        return new UserResource($request->user()->load(['branch:id,name','department:id,name','office:id,name','roles:id,name','address','familyDetails','personalDetails']));
    }

    public function updateProfile(UpdateOwnDetailsRequest $request): UserResource
    {
        return new UserResource($this->employees->updateOwnDetails($request->user(),$request->validated()));
    }

    public function updateSignature(Request $request): UserResource
    {
        $user = $request->user();
        abort_unless($this->canManageSignature($user), 403, 'لا تملك صلاحية إنشاء توقيع رقمي.');

        $data = $request->validate([
            'signature_data' => ['required', 'string'],
        ]);

        if (! preg_match('/^data:image\/png;base64,/', $data['signature_data'])) {
            abort(422, 'صيغة التوقيع غير صحيحة.');
        }

        $raw = base64_decode(Str::after($data['signature_data'], ','), true);
        if ($raw === false || strlen($raw) < 100) {
            abort(422, 'التوقيع فارغ أو غير صالح.');
        }

        if ($user->digital_signature_path) {
            Storage::disk('public')->delete($user->digital_signature_path);
        }

        $path = 'users/signatures/user-' . $user->id . '-' . uniqid() . '.png';
        Storage::disk('public')->put($path, $raw);

        $user->update(['digital_signature_path' => $path]);

        return new UserResource($user->fresh()->load(['branch:id,name','department:id,name','office:id,name','roles:id,name','address','familyDetails','personalDetails']));
    }

    private function canManageSignature(User $user): bool
    {
        return in_array($user->primaryRole(), ['department_head', 'branch_manager', 'general_manager'], true)
            || filled($user->office_id);
    }
}
