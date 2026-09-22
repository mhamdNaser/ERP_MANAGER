<?php

namespace App\Modules\Fleet\Controllers;

use App\Http\Controllers\Controller;
use App\Models\FleetMission;
use App\Modules\Fleet\Repositories\Interfaces\FleetRepositoryInterface;
use App\Modules\Fleet\Requests\StoreFleetMissionRequest;
use App\Modules\Fleet\Services\FleetAccessService;
use App\Modules\Fleet\Services\FleetWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FleetMissionController extends Controller
{
    public function __construct(
        private FleetAccessService $access,
        private FleetWorkflowService $workflow,
        private FleetRepositoryInterface $fleet,
    ) {}

    /** لوحة الموظف: مهامه الشخصية لا غير. */
    public function mine(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'missions' => $this->fleet->forUser($user->id),
            'is_fleet_staff' => $this->access->isFleetStaff($user),
        ]);
    }

    /**
     * لوحة فرع الآليات: الفرع يرى كل المهام ويديرها،
     * والمدير العام يرى ما وصل إليه وما بتّ فيه دون أدوات الإدارة.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $isFleetStaff = $this->access->isFleetStaff($user);
        $isGeneralManager = $user?->role === 'general_manager';
        abort_unless($isFleetStaff || $isGeneralManager, 403, 'هذا الفرع متاح لموظفي الآليات والمدير العام.');

        $missions = $this->fleet->filtered([
            'statuses' => $isFleetStaff ? null : ['pending_gm', 'approved', 'rejected'],
            'status' => $request->string('status')->toString(),
            'user_id' => $request->integer('user_id'),
        ]);

        return response()->json([
            'is_fleet_staff' => $isFleetStaff,
            'missions' => $missions,
            'summary' => [
                'total' => $missions->count(),
                'pending' => $missions->where('status', 'like', 'pending_%')->count(),
                'approved' => $missions->where('status', 'approved')->count(),
                'rejected' => $missions->where('status', 'rejected')->count(),
                'awaiting_fleet' => $missions->where('status', $isFleetStaff ? 'pending_fleet' : 'pending_gm')->count(),
            ],
            'employees' => $isFleetStaff ? $this->fleet->activeEmployees() : [],
        ]);
    }

    public function store(StoreFleetMissionRequest $request): JsonResponse
    {
        $data = $request->validated();
        $actor = $request->user();
        $targetId = (int) ($data['user_id'] ?? $actor->id);

        // التسجيل نيابةً عن موظف آخر حكرٌ على فرع الآليات.
        abort_unless($targetId === (int) $actor->id || $this->access->isFleetStaff($actor), 403, 'تسجيل مهمة نيابة عن موظف متاح لفرع الآليات فقط.');

        $employee = $this->fleet->findEmployee($targetId);

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $data['attachment_path'] = $file->store('fleet-missions', 'public');
            $data['attachment_name'] = $file->getClientOriginalName();
        }

        $stage = $this->workflow->initialStage();
        $mission = $this->fleet->create([
            ...$data,
            'reference_code' => $this->nextReference(),
            'user_id' => $employee->id,
            'created_by_id' => $actor->id,
            'type' => $data['type'] ?? 'mission',
            'days' => $data['days'] ?? $this->countDays($data),
            'stage' => $stage,
            'status' => $this->workflow->statusForStage($stage),
        ]);

        $this->workflow->record($mission, $actor, 'submit', null);
        $this->workflow->notifyStageOwners($mission->load('user'));

        return response()->json($this->fleet->loadRelations($mission), 201);
    }

    public function decide(Request $request, FleetMission $fleetMission): JsonResponse
    {
        abort_unless($this->access->canView($request->user(), $fleetMission), 403, 'لا تملك صلاحية الاطلاع على هذه المهمة.');
        abort_unless($this->access->canDecide($request->user(), $fleetMission->load('user')), 403, 'لا تملك صلاحية البتّ في هذه المرحلة.');

        $data = $request->validate([
            'action' => ['required', 'in:approve,reject'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $updated = $data['action'] === 'approve'
            ? $this->workflow->approve($fleetMission, $request->user(), $data['note'] ?? null)
            : $this->workflow->reject($fleetMission, $request->user(), $data['note'] ?? null);

        return response()->json($this->fleet->loadRelations($updated));
    }

    public function cancel(Request $request, FleetMission $fleetMission): JsonResponse
    {
        abort_unless($this->access->canView($request->user(), $fleetMission), 403, 'لا تملك صلاحية الاطلاع على هذه المهمة.');
        $isOwner = (int) $fleetMission->user_id === (int) $request->user()->id;
        abort_unless($fleetMission->isOpen() && ($isOwner || $this->access->isFleetStaff($request->user())), 403, 'لا يمكن سحب هذه المهمة.');

        return response()->json($this->fleet->loadRelations($this->workflow->cancel($fleetMission, $request->user())));
    }

    private function countDays(array $data): ?float
    {
        if (empty($data['start_date']) || empty($data['end_date'])) {
            return null;
        }

        return (float) (\Carbon\Carbon::parse($data['start_date'])->diffInDays(\Carbon\Carbon::parse($data['end_date'])) + 1);
    }

    private function nextReference(): string
    {
        return 'FL-' . now()->format('Ymd') . '-' . str_pad((string) $this->fleet->nextDailySequence(), 4, '0', STR_PAD_LEFT);
    }
}
