<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Report;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;

class DashboardTimelineSeeder extends Seeder
{
    public function run(): void
    {
        $infra = Department::where('code', 'INFRA')->first();
        $support = Department::where('code', 'SUPPORT')->first();
        $damascusEmployee = User::where('email', 'employee@cnd.local')->first();
        $aleppoEmployee = User::where('email', 'employee2@cnd.local')->first();
        $damascusHead = User::where('email', 'head@cnd.local')->first();
        $aleppoHead = User::where('email', 'head2@cnd.local')->first();

        if (! $infra || ! $support || ! $damascusEmployee || ! $aleppoEmployee || ! $damascusHead || ! $aleppoHead) {
            $this->command?->warn('تعذر إنشاء التسلسل الزمني: شغّل DatabaseSeeder الأساسي أولًا.');
            return;
        }

        $statuses = ['approved', 'approved', 'department_review', 'branch_review', 'returned', 'draft'];

        foreach (range(5, 0) as $monthsAgo) {
            $month = now()->startOfMonth()->subMonths($monthsAgo);
            $volume = 2 + (5 - $monthsAgo);

            foreach (range(1, $volume) as $index) {
                $isDamascus = $index <= (int) ceil($volume * .65);
                $employee = $isDamascus ? $damascusEmployee : $aleppoEmployee;
                $department = $isDamascus ? $infra : $support;
                $createdAt = $month->copy()->addDays(min(25, 2 + ($index * 3)))->addHours(9 + $index);
                $status = $statuses[($index + $monthsAgo) % count($statuses)];
                $title = "تقرير زمني {$month->format('Y-m')} رقم {$index}";

                $report = Report::updateOrCreate(
                    ['employee_id' => $employee->id, 'title' => $title],
                    [
                        'branch_id' => $department->branch_id,
                        'department_id' => $department->id,
                        'type' => $index % 2 ? 'monthly_report' : 'weekly_plan',
                        'period_start' => $month->copy()->startOfMonth()->toDateString(),
                        'period_end' => $month->copy()->endOfMonth()->toDateString(),
                        'summary' => 'بيانات زمنية تجريبية لعرض حركة التقارير بوضوح على الداشبورد.',
                        'achievements' => 'توثيق تقدم العمل ومتابعة المؤشرات التشغيلية.',
                        'challenges' => 'تحديات تشغيلية تجريبية ضمن الفترة.',
                        'next_steps' => 'متابعة التنفيذ خلال الفترة التالية.',
                        'status' => $status,
                        'current_reviewer_role' => $status === 'department_review' ? 'department_head' : ($status === 'branch_review' ? 'branch_manager' : null),
                        'submitted_at' => $status === 'draft' ? null : $createdAt->copy()->addDay(),
                    ],
                );
                $report->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt->copy()->addDays($status === 'approved' ? 3 : 1)])->saveQuietly();
            }
        }

        foreach (range(7, 0) as $weeksAgo) {
            $department = $weeksAgo % 2 ? $infra : $support;
            $creator = $weeksAgo % 2 ? $damascusHead : $aleppoHead;
            $assignee = $weeksAgo % 2 ? $damascusEmployee : $aleppoEmployee;
            $createdAt = now()->startOfWeek()->subWeeks($weeksAgo)->addDay()->addHours(9);
            $approvedAt = $createdAt->copy()->addDays(2);
            $title = "مهمة أرشيفية للأسبوع {$createdAt->format('o-W')}";

            $task = Task::updateOrCreate(
                ['department_id' => $department->id, 'title' => $title],
                [
                    'creator_id' => $creator->id,
                    'assignee_id' => $assignee->id,
                    'description' => 'مهمة مكتملة موزعة زمنيًا لإظهار نشاط الإنجاز الأسبوعي في الداشبورد.',
                    'status' => 'completed',
                    'priority' => $weeksAgo % 3 === 0 ? 'high' : 'medium',
                    'label' => 'أرشيف زمني',
                    'due_date' => $approvedAt->toDateString(),
                    'completed_at' => $approvedAt,
                    'started_at' => $createdAt->copy()->addHours(6),
                    'stage_entered_at' => $approvedAt,
                    'position' => 100 + $weeksAgo,
                ],
            );
            $task->forceFill(['created_at' => $createdAt, 'updated_at' => $approvedAt])->saveQuietly();
        }

        $this->command?->info('تم إنشاء بيانات زمنية واضحة لمخططات الداشبورد.');
    }
}
