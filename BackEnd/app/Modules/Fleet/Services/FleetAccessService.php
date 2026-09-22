<?php

namespace App\Modules\Fleet\Services;

use App\Models\FleetMission;
use App\Models\User;
use App\Modules\Fleet\Repositories\Interfaces\FleetRepositoryInterface;
use Illuminate\Support\Collection;

/**
 * الاطلاع على المهام حكرٌ على ثلاثة أطراف: صاحب المهمة، وفرع الآليات،
 * والمدير العام. لا يراها رؤساء الأقسام ولا غيرهم.
 */
class FleetAccessService
{
    public function __construct(private FleetRepositoryInterface $fleet) {}

    /** موظف الآليات: عضو في الفرع أو يملك صلاحية الإدارة. */
    public function isFleetStaff(?User $user): bool
    {
        if (! $user) return false;

        return $user->can('fleet.manage')
            || ($user->department_id && in_array((int) $user->department_id, $this->fleet->fleetDepartmentIds(), true));
    }

    /** هل يستطيع المستخدم البتّ في المرحلة الحالية لهذه المهمة؟ */
    public function canDecide(?User $user, FleetMission $mission): bool
    {
        if (! $user || ! $mission->isOpen()) return false;

        return match ($mission->stage) {
            'fleet' => $this->isFleetStaff($user) || $user->can('fleet.approve'),
            'gm' => $user->role === 'general_manager',
            default => false,
        };
    }

    /** المستخدمون المعنيّون بالمرحلة الحالية — لإرسال الإشعارات. */
    public function stageApproverIds(FleetMission $mission): Collection
    {
        return match ($mission->stage) {
            'fleet' => $this->fleet->fleetStaffIds(),
            'gm' => $this->fleet->generalManagerIds(),
            default => collect(),
        };
    }

    /** أطراف المسار مجتمعين: فرع الآليات والمدير العام. */
    public function watcherIds(): Collection
    {
        return $this->fleet->watcherIds();
    }

    /** الاطلاع على المهمة حكرٌ على صاحبها وفرع الآليات والمدير العام. */
    public function canView(?User $user, FleetMission $mission): bool
    {
        if (! $user) return false;

        return (int) $mission->user_id === (int) $user->id
            || $this->isFleetStaff($user)
            || $user->role === 'general_manager';
    }
}
