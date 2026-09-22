<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Department;
use App\Models\FleetMission;
use App\Models\User;
use App\Modules\Fleet\Services\FleetWorkflowService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * بيانات تجريبية لفرع الآليات: الفرع ومنتسبوه ومهمة عمل في مسار الاعتماد.
 * الفرع بدأ بمهمة العمل المنقولة من الموارد البشرية، وصلاحياته مهيّأة
 * لما يُضاف إليه لاحقاً من طلبات.
 */
class FleetSeeder extends Seeder
{
    public function run(): void
    {
        $branch = Branch::where('code', 'DAM')->first() ?? Branch::first();
        if (! $branch) return;

        $department = Department::firstOrCreate(
            ['code' => 'FLEET'],
            ['name' => 'فرع الآليات', 'branch_id' => $branch->id],
        );

        $head = $this->ensureUser('fleet@cnd.local', 'سامر الخطيب', 'department_head', 'رئيس فرع الآليات', $branch, $department);
        $officer = $this->ensureUser('fleet.officer@cnd.local', 'وائل منصور', 'employee', 'موظف آليات', $branch, $department);

        foreach ([$head, $officer] as $member) {
            $member->givePermissionTo(['fleet.view', 'fleet.manage', 'fleet.request', 'fleet.approve']);
        }

        $this->seedMissions();
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

    private function seedMissions(): void
    {
        $workflow = app(FleetWorkflowService::class);
        $employees = User::whereIn('email', ['head@cnd.local', 'technician@cnd.local'])->get()->keyBy('email');

        $samples = [
            ['email' => 'head@cnd.local', 'destination' => 'فرع حلب', 'days' => 3, 'offset' => 4,
             'reason' => 'مهمة فنية لمتابعة تركيب المعدات.', 'advance' => 0],
            ['email' => 'technician@cnd.local', 'destination' => 'فرع حمص', 'days' => 2, 'offset' => 1,
             'reason' => 'نقل تجهيزات الشبكة إلى موقع العمل.', 'advance' => 2],
        ];

        foreach ($samples as $index => $sample) {
            $employee = $employees->get($sample['email']);
            if (! $employee || FleetMission::where('user_id', $employee->id)->exists()) {
                continue;
            }

            $start = now()->addDays($sample['offset'])->startOfDay();
            $stage = $workflow->initialStage();
            $mission = FleetMission::create([
                'reference_code' => 'FL-' . now()->format('Ymd') . '-' . str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                'user_id' => $employee->id,
                'created_by_id' => $employee->id,
                'type' => 'mission',
                'start_date' => $start->toDateString(),
                'end_date' => $start->copy()->addDays(max($sample['days'] - 1, 0))->toDateString(),
                'days' => $sample['days'],
                'destination' => $sample['destination'],
                'reason' => $sample['reason'],
                'stage' => $stage,
                'status' => $workflow->statusForStage($stage),
            ]);
            $workflow->record($mission, $employee, 'submit', null);

            // ادفع بعض المهام إلى مراحل متقدمة كي تظهر الحالات كلها.
            for ($step = 0; $step < $sample['advance']; $step++) {
                if (! $mission->isOpen()) break;
                $approver = $this->approverFor($mission);
                if (! $approver) break;
                $mission = $workflow->approve($mission, $approver, 'موافقة ضمن البيانات التجريبية.');
            }
        }
    }

    private function approverFor(FleetMission $mission): ?User
    {
        return match ($mission->stage) {
            'fleet' => User::where('email', 'fleet@cnd.local')->first(),
            'gm' => User::where('role', 'general_manager')->first(),
            default => null,
        };
    }
}
