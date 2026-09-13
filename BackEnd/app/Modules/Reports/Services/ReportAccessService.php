<?php
namespace App\Modules\Reports\Services;

use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ReportAccessService
{
    public function visibleTo(User $user): Builder
    {
        $query = Report::query()->with(['employee:id,name,job_title', 'branch:id,name', 'department:id,name'])->latest();

        return match ($user->primaryRole()) {
            'database_manager' => $query,
            'general_manager' => $query->whereIn('status', ['general_review', 'approved']),
            'branch_manager' => $query->where('branch_id', $user->branch_id)->where('status', 'branch_review'),
            'department_head' => $query->where('department_id', $user->department_id)->where('status', 'department_review'),
            default => $query->where('employee_id', $user->id)->whereIn('status', ['draft', 'returned']),
        };
    }

    public function canManage(User $user, Report $report): bool
    {
        return match ($user->primaryRole()) {
            'database_manager' => true,
            'general_manager' => in_array($report->status, ['general_review', 'approved'], true),
            'branch_manager' => $report->branch_id === $user->branch_id && $report->status === 'branch_review',
            'department_head' => $report->department_id === $user->department_id && $report->status === 'department_review',
            default => $report->employee_id === $user->id && in_array($report->status, ['draft', 'returned']),
        };
    }
}
