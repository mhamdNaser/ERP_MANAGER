<?php
namespace App\Modules\Reports\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Modules\Reports\Repositories\Interfaces\ReportRepositoryInterface;
use App\Modules\Reports\Requests\StoreReportRequest;
use App\Modules\Reports\Requests\TransitionReportRequest;
use App\Modules\Reports\Requests\UpdateReturnedReportRequest;
use App\Modules\Reports\Resources\ReportResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ReportController extends Controller
{
    public function __construct(private ReportRepositoryInterface $reports) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return ReportResource::collection($this->reports->visibleTo($request->user()));
    }

    public function store(StoreReportRequest $request): ReportResource
    {
        return new ReportResource($this->reports->createFor($request->user(), $request->validated())->load(['employee', 'branch', 'department']));
    }

    public function update(UpdateReturnedReportRequest $request, Report $report): ReportResource
    {
        return new ReportResource($this->reports->updateReturned($request->user(), $report, $request->validated()));
    }

    public function transition(TransitionReportRequest $request, Report $report): ReportResource
    {
        return new ReportResource($this->reports->transition($request->user(), $report, $request->validated('action'), $request->validated('note')));
    }
}
