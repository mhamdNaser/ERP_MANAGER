<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Department;
use App\Models\HrLeaveBalance;
use App\Models\HrRequest;
use App\Models\User;
use App\Modules\Hr\Services\HrWorkflowService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * بيانات تجريبية للموارد البشرية: قسم ومنتسبوه وطلبات في مراحل اعتماد مختلفة.
 */
class HrSeeder extends Seeder
{
    public function run(): void
    {
        $branch = Branch::where('code', 'DAM')->first() ?? Branch::first();
        if (! $branch) return;

        $department = Department::firstOrCreate(
            ['code' => 'HR'],
            ['name' => 'قسم الموارد البشرية', 'branch_id' => $branch->id],
        );

        $head = $this->ensureUser('hr@cnd.local', 'ريم الحلبي', 'department_head', 'رئيسة قسم الموارد البشرية', $branch, $department);
        $officer = $this->ensureUser('hr.officer@cnd.local', 'كنان عبد الله', 'employee', 'موظف موارد بشرية', $branch, $department);

        foreach ([$head, $officer] as $member) {
            $member->givePermissionTo(['hr.view', 'hr.manage', 'hr.request', 'hr.approve']);
        }

        $this->seedBalances();
        $this->seedRequests();
    }

    private function ensureUser(string $email, string $name, string $role, string $jobTitle, Branch $branch, Department $department): User
    {
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make('password'),
                'role' => $role,
                'job_title' => $jobTitle,
                'branch_id' => $branch->id,
                'department_id' => $department->id,
                'is_active' => true,
                'employment_type' => 'fixed',
            ],
        );

        if (! $user->hasRole($role)) {
            $user->syncRoles([$role]);
        }

        return $user;
    }

    private function seedBalances(): void
    {
        foreach (User::where('is_active', true)->get() as $user) {
            HrLeaveBalance::firstOrCreate(
                ['user_id' => $user->id, 'year' => now()->year],
                ['annual_entitlement' => 30, 'used_days' => 0],
            );
        }
    }

    private function seedRequests(): void
    {
        $workflow = app(HrWorkflowService::class);
        $employees = User::whereIn('email', [
            'employee@cnd.local', 'employee2@cnd.local', 'technician@cnd.local',
            'head@cnd.local', 'personnel@cnd.local',
        ])->get()->keyBy('email');

        $samples = [
            ['email' => 'employee@cnd.local', 'type' => 'leave', 'subtype' => 'annual', 'days' => 5,
             'reason' => 'إجازة سنوية لقضاء عطلة عائلية.', 'advance' => 3, 'offset' => 7],
            ['email' => 'employee2@cnd.local', 'type' => 'leave', 'subtype' => 'sick', 'days' => 2,
             'reason' => 'إجازة مرضية بتقرير طبي.', 'advance' => 1, 'offset' => 2],
            ['email' => 'technician@cnd.local', 'type' => 'departure',
             'reason' => 'مغادرة لمراجعة دائرة حكومية.', 'advance' => 2, 'offset' => 0],
            ['email' => 'personnel@cnd.local', 'type' => 'document', 'subtype' => 'salary',
             'reason' => 'مطلوبة لتقديمها إلى المصرف.', 'advance' => 3, 'offset' => 0],
        ];

        foreach ($samples as $index => $sample) {
            $employee = $employees->get($sample['email']);
            if (! $employee || HrRequest::where('user_id', $employee->id)->where('type', $sample['type'])->exists()) {
                continue;
            }

            $start = now()->addDays($sample['offset'])->startOfDay();
            $stage = $workflow->initialStage();
            $request = HrRequest::create([
                'reference_code' => 'HR-' . now()->format('Ymd') . '-' . str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                'user_id' => $employee->id,
                'created_by_id' => $employee->id,
                'type' => $sample['type'],
                'subtype' => $sample['subtype'] ?? null,
                'start_date' => $sample['type'] === 'leave' ? $start->toDateString() : null,
                'end_date' => $sample['type'] === 'leave'
                    ? $start->copy()->addDays(max(($sample['days'] ?? 3) - 1, 0))->toDateString() : null,
                'start_time' => $sample['type'] === 'departure' ? '10:00' : null,
                'end_time' => $sample['type'] === 'departure' ? '13:00' : null,
                'days' => $sample['days'] ?? null,
                'hours' => $sample['type'] === 'departure' ? 3 : null,
                'reason' => $sample['reason'],
                'stage' => $stage,
                'status' => $workflow->statusForStage($stage),
            ]);
            $workflow->record($request, $employee, 'submit', null);

            // ادفع بعض الطلبات إلى مراحل متقدمة كي تظهر الحالات كلها.
            for ($step = 0; $step < $sample['advance']; $step++) {
                if (! $request->isOpen()) break;
                $approver = $this->approverFor($request);
                if (! $approver) break;
                $request = $workflow->approve($request, $approver, 'موافقة ضمن البيانات التجريبية.');
            }
        }
    }

    private function approverFor(HrRequest $request): ?User
    {
        return match ($request->stage) {
            'hr' => User::where('email', 'hr@cnd.local')->first(),
            'gm' => User::where('role', 'general_manager')->first(),
            default => null,
        };
    }
}
