<?php
namespace App\Modules\Notifications\Repositories\Eloquent;

use App\Models\CndNotification;
use App\Models\User;
use App\Modules\Notifications\Repositories\Interfaces\NotificationRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class NotificationRepository implements NotificationRepositoryInterface
{
    /** كل ما تحتاجه بطاقة الإشعار لتعرض مصدرها دون طلب إضافي. */
    private const RELATIONS = [
        'report.employee', 'report.branch', 'report.department', 'circular.issuer',
        'customForm.creator', 'customForm.fields',
        'customFormPublication.issuer', 'customFormPublication.branch', 'customFormPublication.department',
        'customFormPublication.form.creator', 'customFormPublication.form.fields',
        'task.department', 'task.assignee',
    ];

    public function visibleTo(User $user): Collection
    {
        return CndNotification::where('user_id', $user->id)->latest()->get()->load(self::RELATIONS);
    }

    public function findFor(User $user, CndNotification $notification): CndNotification
    {
        abort_unless($notification->user_id === $user->id, 403);
        $notification->update(['read_at' => $notification->read_at ?? now()]);
        return $notification->fresh()->load(self::RELATIONS);
    }

    public function markAllRead(User $user): void
    {
        CndNotification::where('user_id', $user->id)->whereNull('read_at')->update(['read_at' => now()]);
    }

    public function recentFor(User $user, int $limit): Collection
    {
        return CndNotification::where('user_id', $user->id)->latest()->limit($limit)->get()
            ->load(['report.employee', 'report.branch', 'report.department', 'circular.issuer']);
    }

    public function unreadCountFor(User $user): int
    {
        return CndNotification::where('user_id', $user->id)->whereNull('read_at')->count();
    }

    public function create(array $data): CndNotification
    {
        return CndNotification::create($data);
    }
}
