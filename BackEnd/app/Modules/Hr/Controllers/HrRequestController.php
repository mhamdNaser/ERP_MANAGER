<?php

namespace App\Modules\Hr\Controllers;

use App\Http\Controllers\Controller;
use App\Models\HrRequest;
use App\Models\User;
use App\Modules\Hr\Repositories\Interfaces\HrRepositoryInterface;
use App\Modules\Hr\Requests\StoreHrRequestRequest;
use App\Modules\Hr\Services\HrAccessService;
use App\Modules\Hr\Services\HrWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrRequestController extends Controller
{
    public function __construct(
        private HrAccessService $access,
        private HrWorkflowService $workflow,
        private HrRepositoryInterface $hr,
    ) {}

    /** لوحة الموظف: طلباته الشخصية ورصيده لا غير. */
    public function mine(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'requests' => $this->hr->forUser($user->id),
            'balance' => $this->workflow->balanceFor($user->id),
            'is_hr_staff' => $this->access->isHrStaff($user),
        ]);
    }

    /**
     * لوحة الموارد البشرية: القسم يرى كل الطلبات ويديرها،
     * والمدير العام يرى ما وصل إليه وما بتّ فيه دون أدوات الإدارة.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $isHrStaff = $this->access->isHrStaff($user);
        $isGeneralManager = $user?->role === 'general_manager';
        abort_unless($isHrStaff || $isGeneralManager, 403, 'هذا القسم متاح للموارد البشرية والمدير العام.');

        $requests = $this->hr->filtered([
            'statuses' => $isHrStaff ? null : ['pending_gm', 'approved', 'rejected'],
            'type' => $request->string('type')->toString(),
            'status' => $request->string('status')->toString(),
            'user_id' => $request->integer('user_id'),
        ]);

        return response()->json([
            'is_hr_staff' => $isHrStaff,
            'requests' => $requests,
            'summary' => [
                'total' => $requests->count(),
                'pending' => $requests->where('status', 'like', 'pending_%')->count(),
                'approved' => $requests->where('status', 'approved')->count(),
                'rejected' => $requests->where('status', 'rejected')->count(),
                'awaiting_hr' => $requests->where('status', $isHrStaff ? 'pending_hr' : 'pending_gm')->count(),
            ],
            'balances' => $isHrStaff ? $this->hr->balancesForYear((int) now()->year) : [],
            'employees' => $isHrStaff ? $this->hr->activeEmployees() : [],
        ]);
    }

    public function store(StoreHrRequestRequest $request): JsonResponse
    {
        $data = $request->validated();
        $actor = $request->user();
        $targetId = (int) ($data['user_id'] ?? $actor->id);

        // التسجيل نيابةً عن موظف آخر حكرٌ على الموارد البشرية.
        abort_unless($targetId === (int) $actor->id || $this->access->isHrStaff($actor), 403, 'تسجيل طلب نيابة عن موظف متاح للموارد البشرية فقط.');

        $employee = $this->hr->findEmployee($targetId);

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $data['attachment_path'] = $file->store('hr-requests', 'public');
            $data['attachment_name'] = $file->getClientOriginalName();
        }

        $stage = $this->workflow->initialStage();
        $hrRequest = $this->hr->create([
            ...$data,
            'reference_code' => $this->nextReference(),
            'user_id' => $employee->id,
            'created_by_id' => $actor->id,
            'days' => $data['days'] ?? $this->countDays($data),
            'stage' => $stage,
            'status' => $this->workflow->statusForStage($stage),
        ]);

        $this->workflow->record($hrRequest, $actor, 'submit', null);
        $this->workflow->notifyStageOwners($hrRequest->load('user'));

        return response()->json($this->hr->loadRelations($hrRequest), 201);
    }

    public function decide(Request $request, HrRequest $hrRequest): JsonResponse
    {
        abort_unless($this->access->canView($request->user(), $hrRequest), 403, 'لا تملك صلاحية الاطلاع على هذا الطلب.');
        abort_unless($this->access->canDecide($request->user(), $hrRequest->load('user')), 403, 'لا تملك صلاحية البتّ في هذه المرحلة.');

        $data = $request->validate([
            'action' => ['required', 'in:approve,reject'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $updated = $data['action'] === 'approve'
            ? $this->workflow->approve($hrRequest, $request->user(), $data['note'] ?? null)
            : $this->workflow->reject($hrRequest, $request->user(), $data['note'] ?? null);

        return response()->json($this->hr->loadRelations($updated));
    }

    public function cancel(Request $request, HrRequest $hrRequest): JsonResponse
    {
        abort_unless($this->access->canView($request->user(), $hrRequest), 403, 'لا تملك صلاحية الاطلاع على هذا الطلب.');
        $isOwner = (int) $hrRequest->user_id === (int) $request->user()->id;
        abort_unless($hrRequest->isOpen() && ($isOwner || $this->access->isHrStaff($request->user())), 403, 'لا يمكن سحب هذا الطلب.');

        return response()->json($this->hr->loadRelations($this->workflow->cancel($hrRequest, $request->user())));
    }

    public function updateBalance(Request $request, User $employee): JsonResponse
    {
        abort_unless($this->access->isHrStaff($request->user()), 403, 'تعديل الأرصدة متاح للموارد البشرية فقط.');

        $data = $request->validate([
            'annual_entitlement' => ['required', 'numeric', 'min:0', 'max:365'],
            'used_days' => ['nullable', 'numeric', 'min:0', 'max:365'],
        ]);

        $balance = $this->workflow->balanceFor($employee->id);

        return response()->json($this->hr->updateBalance($balance, [
            'annual_entitlement' => $data['annual_entitlement'],
            'used_days' => $data['used_days'] ?? $balance->used_days,
        ]));
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
        return 'HR-' . now()->format('Ymd') . '-' . str_pad((string) $this->hr->nextDailySequence(), 4, '0', STR_PAD_LEFT);
    }
}
