<?php

namespace App\Modules\Permissions\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Department;
use App\Models\TabAccessGrant;
use App\Models\User;
use App\Modules\Permissions\Services\TabAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** جمهور كل تبويب: الجميع، أو أفرع، أو أقسام داخل فرع، أو موظفون — معاً — وبأي مستوى. */
class TabAccessController extends Controller
{
    public function __construct(private TabAccessService $access) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'tabs' => collect(TabAccessService::tabs())->map(fn (array $definition, string $tab) => [
                'id' => $tab,
                'has_manage' => ! empty($definition['manage']) && empty($definition['role']),
                'role_limited' => ! empty($definition['role']),
                'view' => $definition['view'] ?? [],
                'manage' => $definition['manage'] ?? [],
            ])->values(),
            'grants' => TabAccessGrant::with(['branch:id,name', 'department:id,name', 'user:id,name,job_title,department_id,branch_id'])->orderBy('id')->get(),
            'branches' => Branch::orderBy('name')->get(['id', 'name']),
            'departments' => Department::orderBy('name')->get(['id', 'name', 'branch_id']),
            'users' => User::where('is_active', true)->orderBy('name')->get(['id', 'name', 'job_title', 'department_id', 'branch_id']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tab' => ['required', Rule::in(array_keys(TabAccessService::tabs()))],
            'everyone' => ['nullable', 'boolean'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'level' => ['nullable', Rule::in(TabAccessGrant::LEVELS)],
        ]);
        $data['everyone'] = (bool) ($data['everyone'] ?? false);
        $targets = array_filter(['everyone' => $data['everyone'] ?: null, 'branch_id' => $data['branch_id'] ?? null, 'department_id' => $data['department_id'] ?? null, 'user_id' => $data['user_id'] ?? null]);
        abort_unless(count($targets) === 1, 422, 'الربط يكون لواحد فقط: الجميع أو فرع أو قسم أو شخص.');

        // NULL لا يتكرر في الفهرس الفريد، فيُمنع التكرار هنا صراحةً.
        $grant = TabAccessGrant::updateOrCreate(
            ['tab' => $data['tab'], 'everyone' => $data['everyone'], 'branch_id' => $data['branch_id'] ?? null, 'department_id' => $data['department_id'] ?? null, 'user_id' => $data['user_id'] ?? null],
            ['level' => $this->level($data['tab'], $data['level'] ?? 'view'), 'created_by_id' => $request->user()->id],
        );
        $this->access->forget();

        return response()->json($grant->load(['branch:id,name', 'department:id,name', 'user:id,name,job_title,department_id,branch_id']), 201);
    }

    public function update(Request $request, TabAccessGrant $grant): JsonResponse
    {
        $data = $request->validate(['level' => ['required', Rule::in(TabAccessGrant::LEVELS)]]);
        $grant->update(['level' => $this->level($grant->tab, $data['level'])]);
        $this->access->forget();

        return response()->json($grant->load(['branch:id,name', 'department:id,name', 'user:id,name,job_title,department_id,branch_id']));
    }

    public function destroy(TabAccessGrant $grant): JsonResponse
    {
        $grant->delete();
        $this->access->forget();

        return response()->json(['deleted' => true]);
    }

    /** تبويب بلا مستوى إدارة يبقى على الاطلاع مهما طُلب. */
    private function level(string $tab, string $level): string
    {
        $definition = TabAccessService::tabs()[$tab];

        return $level === 'manage' && ! empty($definition['manage']) && empty($definition['role']) ? 'manage' : 'view';
    }
}
