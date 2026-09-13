<?php

namespace App\Modules\Hr\Services;

use App\Models\HrRequest;
use App\Models\User;
use App\Modules\Hr\Repositories\Interfaces\HrRepositoryInterface;
use Illuminate\Support\Collection;

/**
 * الاطلاع على الطلبات حكرٌ على ثلاثة أطراف: صاحب الطلب، والموارد البشرية،
 * والمدير العام. لا يراها رؤساء الأقسام ولا غيرهم.
 */
class HrAccessService
{
    public function __construct(private HrRepositoryInterface $hr) {}

    /** موظف الموارد البشرية: عضو في القسم أو يملك صلاحية الإدارة. */
    public function isHrStaff(?User $user): bool
    {
        if (! $user) return false;

        return $user->can('hr.manage')
            || ($user->department_id && in_array((int) $user->department_id, $this->hr->hrDepartmentIds(), true));
    }

    /** هل يستطيع المستخدم البتّ في المرحلة الحالية لهذا الطلب؟ */
    public function canDecide(?User $user, HrRequest $request): bool
    {
        if (! $user || ! $request->isOpen()) return false;

        return match ($request->stage) {
            'hr' => $this->isHrStaff($user),
            'gm' => $user->role === 'general_manager',
            default => false,
        };
    }

    /** المستخدمون المعنيّون بالمرحلة الحالية — لإرسال الإشعارات. */
    public function stageApproverIds(HrRequest $request): Collection
    {
        return match ($request->stage) {
            'hr' => $this->hr->hrStaffIds(),
            'gm' => $this->hr->generalManagerIds(),
            default => collect(),
        };
    }

    /** أطراف المسار مجتمعين: الموارد البشرية والمدير العام. */
    public function watcherIds(): Collection
    {
        return $this->hr->watcherIds();
    }

    /** الاطلاع على الطلب حكرٌ على صاحبه والموارد البشرية والمدير العام. */
    public function canView(?User $user, HrRequest $request): bool
    {
        if (! $user) return false;

        return (int) $request->user_id === (int) $user->id
            || $this->isHrStaff($user)
            || $user->role === 'general_manager';
    }
}
