<?php
namespace App\Modules\Notifications\Repositories\Interfaces;

use App\Models\CndNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface NotificationRepositoryInterface
{
    public function visibleTo(User $user): Collection;
    public function findFor(User $user, CndNotification $notification): CndNotification;
    public function markAllRead(User $user): void;

    /** أحدث الإشعارات للوحة الرئيسية. */
    public function recentFor(User $user, int $limit): Collection;
    public function unreadCountFor(User $user): int;

    /** مدخل الكتابة الوحيد لجدول الإشعارات — تستعمله بقية الوحدات. */
    public function create(array $data): CndNotification;
}
