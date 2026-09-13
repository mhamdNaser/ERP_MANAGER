<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Task;
use App\Models\TaskStageTransition;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class TaskSeeder extends Seeder
{
    /** مسار المهمة الطبيعي، تُبنى منه سلسلة الانتقالات التجريبية. */
    private const PIPELINE = ['archived', 'planned', 'in_progress', 'communication', 'review', 'completed'];

    public function run(): void
    {
        $infra = Department::where('code', 'INFRA')->first();
        $support = Department::where('code', 'SUPPORT')->first();

        if (! $infra || ! $support) {
            $this->command?->warn('تعذر إنشاء مهام تجريبية: يجب تشغيل DatabaseSeeder أولًا لإنشاء الأقسام.');
            return;
        }

        $users = User::whereIn('email', [
            'employee@cnd.local',
            'technician@cnd.local',
            'head@cnd.local',
            'employee2@cnd.local',
            'employee3@cnd.local',
            'head2@cnd.local',
        ])->get()->keyBy('email');

        $this->seedDepartment($infra, $users['head@cnd.local'] ?? null, [
            [
                'title' => 'حصر طلبات التوسعة الواردة من الأقسام',
                'description' => 'تجميع الطلبات وأرشفتها بانتظار أن يخطط لها المكلّف.',
                'assignee' => 'employee@cnd.local',
                'status' => 'archived',
                'priority' => 'low',
                'label' => 'أرشفة',
                'due_date' => now()->addDays(12)->toDateString(),
                'age_days' => 2,
            ],
            [
                'title' => 'مراجعة جاهزية النسخ الاحتياطي',
                'description' => 'اختبار آخر نسخة احتياطية وتوثيق نتيجة الاستعادة على بيئة الاختبار.',
                'assignee' => 'employee@cnd.local',
                'status' => 'planned',
                'priority' => 'urgent',
                'label' => 'استمرارية الأعمال',
                'due_date' => now()->addDay()->toDateString(),
                'age_days' => 3,
            ],
            [
                'title' => 'تحديث مخطط عناوين الشبكة',
                'description' => 'تدقيق العناوين المستخدمة وإضافة الأجهزة الجديدة إلى المخطط المركزي.',
                'assignee' => 'technician@cnd.local',
                'status' => 'planned',
                'priority' => 'medium',
                'label' => 'توثيق',
                'due_date' => now()->addDays(5)->toDateString(),
                'age_days' => 4,
            ],
            [
                'title' => 'تجهيز خطة صيانة المولد الاحتياطي',
                'description' => 'التنسيق مع الصيانة ووضع قائمة تحقق شهرية للفحص والتشغيل التجريبي.',
                'status' => 'planned',
                'priority' => 'low',
                'label' => 'صيانة',
                'due_date' => now()->addWeek()->toDateString(),
                'age_days' => 5,
            ],
            [
                'title' => 'ترقية السويتش الرئيسي في الطابق الثاني',
                'description' => 'نقل الإعدادات، اختبار الاتصال، ومراقبة الأداء بعد الترقية.',
                'assignee' => 'technician@cnd.local',
                'status' => 'in_progress',
                'priority' => 'high',
                'label' => 'شبكات',
                'due_date' => now()->addDays(2)->toDateString(),
                'age_days' => 6,
            ],
            [
                'title' => 'معالجة تنبيهات امتلاء وحدة التخزين',
                'description' => 'تحليل الملفات الكبيرة، أرشفة السجلات القديمة، وضبط حدود التنبيه.',
                'assignee' => 'employee@cnd.local',
                'status' => 'in_progress',
                'priority' => 'urgent',
                'label' => 'مخدمات',
                'due_date' => now()->subDay()->toDateString(),
                'age_days' => 9,
            ],
            [
                'title' => 'مخاطبة المتعهد بخصوص قطع الغيار',
                'description' => 'بانتظار موظف التواصل لمخاطبة المتعهد وتثبيت موعد التسليم.',
                'assignee' => 'technician@cnd.local',
                'communication' => 'employee@cnd.local',
                'status' => 'communication',
                'priority' => 'high',
                'label' => 'تعاقد',
                'due_date' => now()->addDays(3)->toDateString(),
                'age_days' => 7,
            ],
            [
                'title' => 'تقرير جاهزية غرفة المخدمات',
                'description' => 'اكتمل التواصل مع الجهات المعنية والمهمة الآن قيد التدقيق قبل اعتمادها.',
                'assignee' => 'employee@cnd.local',
                'communication' => 'technician@cnd.local',
                'status' => 'review',
                'priority' => 'medium',
                'label' => 'تقارير',
                'due_date' => now()->addDays(4)->toDateString(),
                'age_days' => 8,
            ],
            [
                'title' => 'توثيق إجراءات استعادة الخدمة',
                'description' => 'تم إعداد دليل مبسط للحالات الأكثر تكرارًا ومراجعته مع الفريق.',
                'assignee' => 'head@cnd.local',
                'communication' => 'technician@cnd.local',
                'status' => 'completed',
                'priority' => 'medium',
                'label' => 'إجراءات',
                'due_date' => now()->subDays(3)->toDateString(),
                'completed_at' => now()->subDays(2),
                'age_days' => 11,
            ],
            [
                'title' => 'فحص نقاط الاتصال اللاسلكية',
                'description' => 'تم قياس التغطية ومعالجة نقاط الضعف في قاعة الاجتماعات.',
                'assignee' => 'technician@cnd.local',
                'communication' => 'employee@cnd.local',
                'status' => 'completed',
                'priority' => 'low',
                'label' => 'شبكات',
                'due_date' => now()->subWeek()->toDateString(),
                'completed_at' => now()->subDays(5),
                'age_days' => 16,
            ],
            [
                'title' => 'استبدال نظام المراقبة القديم',
                'description' => 'ألغيت المهمة بعد اعتماد تطوير النظام الحالي بدل استبداله.',
                'status' => 'cancelled',
                'priority' => 'low',
                'label' => 'ملغاة',
                'due_date' => now()->subDays(4)->toDateString(),
                'age_days' => 10,
            ],
        ], $users);

        $this->seedDepartment($support, $users['head2@cnd.local'] ?? null, [
            [
                'title' => 'أرشفة تذاكر الربع الماضي',
                'description' => 'حفظ التذاكر المغلقة وتصنيفها بانتظار خطة المعالجة.',
                'assignee' => 'employee3@cnd.local',
                'status' => 'archived',
                'priority' => 'low',
                'label' => 'أرشفة',
                'due_date' => now()->addDays(10)->toDateString(),
                'age_days' => 3,
            ],
            [
                'title' => 'تحديث قاعدة المعرفة للأسئلة المتكررة',
                'description' => 'إضافة حلول التذاكر الأكثر تكرارًا وتصنيفها حسب النظام.',
                'assignee' => 'employee2@cnd.local',
                'status' => 'planned',
                'priority' => 'medium',
                'label' => 'قاعدة المعرفة',
                'due_date' => now()->addDays(4)->toDateString(),
                'age_days' => 4,
            ],
            [
                'title' => 'تجهيز نموذج تسليم الأجهزة الجديدة',
                'description' => 'توحيد خطوات التسليم وربطها بتوثيق العهدة التقنية.',
                'assignee' => 'employee3@cnd.local',
                'status' => 'planned',
                'priority' => 'high',
                'label' => 'أجهزة',
                'due_date' => now()->addDays(3)->toDateString(),
                'age_days' => 5,
            ],
            [
                'title' => 'متابعة تذاكر البريد الإلكتروني المتأخرة',
                'description' => 'حل التذاكر القديمة وإرسال ملخص بالأسباب والإجراءات الوقائية.',
                'assignee' => 'employee2@cnd.local',
                'status' => 'in_progress',
                'priority' => 'urgent',
                'label' => 'دعم المستخدمين',
                'due_date' => now()->toDateString(),
                'age_days' => 12,
            ],
            [
                'title' => 'تنسيق زيارة الدعم لفرع حلب',
                'description' => 'مهمة محوّلة إلى موظف التواصل لتثبيت الموعد مع الفرع.',
                'assignee' => 'employee3@cnd.local',
                'communication' => 'employee2@cnd.local',
                'status' => 'communication',
                'priority' => 'medium',
                'label' => 'تنسيق',
                'due_date' => now()->addDays(6)->toDateString(),
                'age_days' => 6,
            ],
            [
                'title' => 'تثبيت تحديثات أجهزة فريق المحاسبة',
                'description' => 'تم تثبيت التحديثات واختبار البرامج الأساسية مع المستخدمين.',
                'assignee' => 'employee3@cnd.local',
                'communication' => 'employee2@cnd.local',
                'status' => 'completed',
                'priority' => 'high',
                'label' => 'تحديثات',
                'due_date' => now()->subDays(2)->toDateString(),
                'completed_at' => now()->subDay(),
                'age_days' => 9,
            ],
            [
                'title' => 'إغلاق طلبات الدعم القديمة',
                'description' => 'تمت مراجعة الطلبات المؤرشفة وإغلاق المكتمل منها بعد تأكيد أصحابها.',
                'assignee' => 'head2@cnd.local',
                'communication' => 'employee3@cnd.local',
                'status' => 'completed',
                'priority' => 'low',
                'label' => 'متابعة',
                'due_date' => now()->subWeek()->toDateString(),
                'completed_at' => now()->subDays(4),
                'age_days' => 14,
            ],
            [
                'title' => 'تجربة منصة دعم خارجية',
                'description' => 'ألغيت التجربة بعد اعتماد المنصة الداخلية.',
                'status' => 'cancelled',
                'priority' => 'medium',
                'label' => 'ملغاة',
                'due_date' => now()->subDays(2)->toDateString(),
                'age_days' => 8,
            ],
        ], $users);

        $this->command?->info('تم إنشاء بيانات لوحة المهام التجريبية.');
    }

    private function seedDepartment(Department $department, ?User $creator, array $tasks, $users): void
    {
        $creator ??= $department->users()->first();

        if (! $creator) {
            return;
        }

        foreach ($tasks as $position => $data) {
            $assignee = $users[$data['assignee'] ?? ''] ?? null;
            $communicationUser = $users[$data['communication'] ?? ''] ?? null;
            $ageDays = $data['age_days'] ?? 6;
            unset($data['assignee'], $data['communication'], $data['age_days']);

            $task = Task::updateOrCreate(
                ['department_id' => $department->id, 'title' => $data['title']],
                [
                    ...$data,
                    'creator_id' => $creator->id,
                    'assignee_id' => $assignee?->id,
                    'communication_user_id' => $communicationUser?->id,
                    'position' => $position,
                    // يُكتب صراحةً حتى لا يبقى ختم اعتماد قديم على مهمة أعيد
                    // زرعها في مرحلة أبكر عند تشغيل البذور مرة ثانية.
                    'completed_at' => $data['completed_at'] ?? null,
                ],
            );

            $task->forceFill(['created_at' => now()->subDays($ageDays)])->saveQuietly();
            $this->seedTransitions($task);
        }
    }

    /**
     * سلسلة انتقالات تجريبية تغطي المسار حتى الحالة الحالية، موزعة زمنيًا بين
     * إنشاء المهمة ونهايتها — منها تُبنى أرقام لوحة الإحصائيات (سرعة الإنجاز،
     * زمن كل مرحلة) بدل أن تظهر فارغة على قاعدة بيانات جديدة.
     */
    private function seedTransitions(Task $task): void
    {
        if ($task->transitions()->exists()) {
            return;
        }

        $path = $task->status === 'cancelled'
            ? ['archived', 'planned', 'cancelled']
            : array_slice(self::PIPELINE, 0, (int) array_search($task->status, self::PIPELINE, true) + 1);

        $start = $task->created_at;
        $end = $task->completed_at ?? Carbon::now();
        $step = max(1, intdiv((int) $start->diffInSeconds($end), max(1, count($path))));

        $at = $start->copy();
        $from = null;
        foreach ($path as $status) {
            TaskStageTransition::create([
                'task_id' => $task->id,
                'department_id' => $task->department_id,
                'actor_id' => $this->actorFor($task, $from),
                'assignee_id' => $task->assignee_id,
                'communication_user_id' => $task->communication_user_id,
                'from_status' => $from,
                'to_status' => $status,
                'seconds_in_previous' => $from === null ? null : $step,
                'note' => $from === 'communication' ? 'تم إنجاز التواصل المطلوب ورفع المهمة للتدقيق.' : null,
            ])->forceFill(['created_at' => $at, 'updated_at' => $at])->saveQuietly();

            $from = $status;
            $at = $at->copy()->addSeconds($step);
        }

        $task->forceFill([
            'stage_entered_at' => $at->copy()->subSeconds($step),
            'started_at' => in_array('in_progress', $path, true)
                ? $start->copy()->addSeconds($step * 2)
                : null,
        ])->saveQuietly();
    }

    /** من نفّذ الانتقال: صاحب المرحلة التي غادرتها المهمة. */
    private function actorFor(Task $task, ?string $from): ?int
    {
        return match ($from) {
            null => $task->creator_id,
            'communication' => $task->communication_user_id ?? $task->creator_id,
            'review' => $task->creator_id,
            default => $task->assignee_id ?? $task->creator_id,
        };
    }
}
