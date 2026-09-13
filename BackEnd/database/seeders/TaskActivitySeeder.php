<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\User;
use Illuminate\Database\Seeder;

class TaskActivitySeeder extends Seeder
{
    public function run(): void
    {
        $infra = Department::where('code', 'INFRA')->first();
        $support = Department::where('code', 'SUPPORT')->first();

        if (! $infra || ! $support) {
            $this->command?->warn('تعذر إنشاء سجل حركات تجريبي: يجب إنشاء الأقسام أولاً.');
            return;
        }

        $users = User::whereIn('email', [
            'employee@cnd.local',
            'technician@cnd.local',
            'head@cnd.local',
            'employee2@cnd.local',
            'employee3@cnd.local',
            'head2@cnd.local',
            'branch@cnd.local',
        ])->get()->keyBy('email');

        $this->seedActivity(
            $this->task($infra, 'ترقية السويتش الرئيسي في الطابق الثاني'),
            $users['head@cnd.local'] ?? null,
            'task_created',
            'أنشأ المهمة.',
            [],
            now()->subDays(12)->setTime(9, 15),
        );
        $this->seedActivity(
            $this->task($infra, 'ترقية السويتش الرئيسي في الطابق الثاني'),
            $users['technician@cnd.local'] ?? null,
            'task_moved',
            'نقل المهمة إلى مرحلة جديدة.',
            ['from_status' => 'planned', 'to_status' => 'in_progress'],
            now()->subDays(10)->setTime(11, 40),
        );
        $this->seedActivity(
            $this->task($infra, 'ترقية السويتش الرئيسي في الطابق الثاني'),
            $users['technician@cnd.local'] ?? null,
            'file_attached',
            'أرفق ملفاً بالمهمة.',
            ['file_id' => 9001, 'file_name' => 'مخطط-ترقية-السويتش.pdf', 'file_size' => 1843200],
            now()->subDays(9)->setTime(13, 5),
        );
        $this->seedActivity(
            $this->task($infra, 'ترقية السويتش الرئيسي في الطابق الثاني'),
            $users['head@cnd.local'] ?? null,
            'task_updated',
            'عدّل بيانات المهمة.',
            ['changes' => [
                'priority' => ['before' => 'medium', 'after' => 'high'],
                'due_date' => ['before' => now()->addDays(5)->toDateString(), 'after' => now()->addDays(2)->toDateString()],
                'description' => ['before' => null, 'after' => 'إضافة خطوات الاختبار بعد الترقية.'],
            ]],
            now()->subDays(7)->setTime(10, 20),
        );
        $this->seedActivity(
            $this->task($infra, 'معالجة تنبيهات امتلاء وحدة التخزين'),
            $users['employee@cnd.local'] ?? null,
            'file_attached',
            'أرفق ملفاً بالمهمة.',
            ['file_id' => 9002, 'file_name' => 'تحليل-استهلاك-التخزين.xlsx', 'file_size' => 524288],
            now()->subDays(5)->setTime(8, 35),
        );
        $this->seedActivity(
            $this->task($infra, 'معالجة تنبيهات امتلاء وحدة التخزين'),
            $users['employee@cnd.local'] ?? null,
            'file_deleted',
            'حذف ملفاً مرفقاً بالمهمة.',
            ['file_id' => 9003, 'file_name' => 'نسخة-تحليل-قديمة.xlsx', 'file_size' => 410624],
            now()->subDays(4)->setTime(14, 10),
        );
        $this->seedActivity(
            $this->task($infra, 'توثيق إجراءات استعادة الخدمة'),
            $users['head@cnd.local'] ?? null,
            'task_moved',
            'نقل المهمة إلى مرحلة جديدة.',
            ['from_status' => 'review', 'to_status' => 'completed'],
            now()->subDays(2)->setTime(16, 25),
        );
        $this->seedActivity(
            null,
            $users['branch@cnd.local'] ?? null,
            'task_deleted',
            'حذف المهمة.',
            ['files_count' => 2],
            now()->subDay()->setTime(12, 45),
            $infra,
            'تجربة ربط قديمة لم تعد مطلوبة',
        );

        $this->seedActivity(
            $this->task($support, 'تحديث قاعدة المعرفة للأسئلة المتكررة'),
            $users['head2@cnd.local'] ?? null,
            'task_created',
            'أنشأ المهمة.',
            [],
            now()->subDays(8)->setTime(9, 30),
        );
        $this->seedActivity(
            $this->task($support, 'متابعة تذاكر البريد الإلكتروني المتأخرة'),
            $users['employee2@cnd.local'] ?? null,
            'task_updated',
            'عدّل بيانات المهمة.',
            ['changes' => [
                'assignee_id' => ['before' => null, 'after' => $users['employee2@cnd.local']?->id],
                'priority' => ['before' => 'high', 'after' => 'urgent'],
            ]],
            now()->subDays(3)->setTime(15, 0),
        );
        $this->seedActivity(
            $this->task($support, 'تثبيت تحديثات أجهزة فريق المحاسبة'),
            $users['employee3@cnd.local'] ?? null,
            'task_moved',
            'نقل المهمة إلى مرحلة جديدة.',
            ['from_status' => 'review', 'to_status' => 'completed'],
            now()->subHours(7),
        );

        $this->command?->info('تم إنشاء سجل حركات لوحة المهام التجريبي.');
    }

    private function task(Department $department, string $title): ?Task
    {
        return Task::where('department_id', $department->id)->where('title', $title)->first();
    }

    private function seedActivity(
        ?Task $task,
        ?User $actor,
        string $action,
        string $summary,
        array $details,
        $createdAt,
        ?Department $department = null,
        ?string $taskTitle = null,
    ): void {
        $department ??= $task?->department;
        $taskTitle ??= $task?->title;

        if (! $department || ! $actor || ! $taskTitle) {
            return;
        }

        $activity = TaskActivity::updateOrCreate(
            [
                'department_id' => $department->id,
                'actor_id' => $actor->id,
                'action' => $action,
                'task_title' => $taskTitle,
                'summary' => $summary,
            ],
            [
                'task_id' => $task?->id,
                'details' => $details ?: null,
            ],
        );

        $activity->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->saveQuietly();
    }
}
