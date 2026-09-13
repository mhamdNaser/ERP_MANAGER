<?php

namespace App\Modules\Forms\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CustomForm;
use App\Models\CustomFormPublication;
use App\Models\User;
use App\Modules\Forms\Repositories\Interfaces\FormRepositoryInterface;
use App\Modules\Forms\Services\CustomFormService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomFormController extends Controller
{
    public function __construct(private CustomFormService $forms, private FormRepositoryInterface $repository) {}

    public function index(Request $request): JsonResponse
    {
        [$templates, $assigned, $history] = $this->forms->formsFor($request->user());

        return response()->json([
            'templates' => $templates->filter(fn (CustomForm $form) => $this->forms->accessibleTemplate($request->user(), $form))->values(),
            'assigned' => $assigned->values(),
            'history' => $history->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->primaryRole() === 'database_manager', 403);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'target_group' => ['required', 'in:employees,managers,branch_managers,branch_managers_heads,branch_managers_heads_employees,fixed_employees,contract_employees,all_staff'],
            'default_scope' => ['required', 'in:organization,branch,department'],
            'default_duration_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'is_active' => ['boolean'],
            'fields' => ['required', 'array', 'min:1'],
            'fields.*.label' => ['required', 'string', 'max:255'],
            'fields.*.input_type' => ['required', 'in:text,email,password,date,select,textarea,number,checkbox,checkbox_group,radio'],
            'fields.*.is_required' => ['boolean'],
            'fields.*.placeholder' => ['nullable', 'string', 'max:255'],
            'fields.*.help_text' => ['nullable', 'string'],
            'fields.*.options' => ['nullable', 'array'],
        ]);

        return response()->json($this->forms->createTemplate($request->user(), $data), 201);
    }

    public function update(Request $request, CustomForm $form): JsonResponse
    {
        abort_unless($request->user()->primaryRole() === 'database_manager' && $this->forms->accessibleTemplate($request->user(), $form), 403);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'target_group' => ['required', 'in:employees,managers,branch_managers,branch_managers_heads,branch_managers_heads_employees,fixed_employees,contract_employees,all_staff'],
            'default_scope' => ['required', 'in:organization,branch,department'],
            'default_duration_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'is_active' => ['boolean'],
            'fields' => ['required', 'array', 'min:1'],
            'fields.*.label' => ['required', 'string', 'max:255'],
            'fields.*.input_type' => ['required', 'in:text,email,password,date,select,textarea,number,checkbox,checkbox_group,radio'],
            'fields.*.is_required' => ['boolean'],
            'fields.*.placeholder' => ['nullable', 'string', 'max:255'],
            'fields.*.help_text' => ['nullable', 'string'],
            'fields.*.options' => ['nullable', 'array'],
        ]);

        return response()->json($this->forms->updateTemplate($form, $data));
    }

    public function destroy(Request $request, CustomForm $form): JsonResponse
    {
        abort_unless(in_array($request->user()->primaryRole(), ['database_manager', 'general_manager'], true), 403);
        abort_unless($request->user()->primaryRole() === 'general_manager' || $this->forms->accessibleTemplate($request->user(), $form), 403);

        $this->forms->deleteTemplate($form);

        return response()->json(['message' => __('messages.report_deleted')]);
    }

    public function publish(Request $request, CustomForm $form): JsonResponse
    {
        $role = $request->user()->primaryRole();
        abort_unless(in_array($role, ['database_manager', 'general_manager', 'branch_manager', 'department_head'], true), 403);
        abort_unless($form->is_active, 403);

        $data = $request->validate([
            'scope' => ['required', 'in:organization,branch,department'],
            'target_group' => ['required', 'in:employees,managers,branch_managers,branch_managers_heads,branch_managers_heads_employees,fixed_employees,contract_employees,all_staff'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'duration_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'visible_from' => ['nullable', 'date'],
            'visible_until' => ['nullable', 'date', 'after_or_equal:visible_from'],
            'message' => ['nullable', 'string'],
        ]);

        if ($role === 'branch_manager') {
            $data['scope'] = 'branch';
            $data['branch_id'] = $request->user()->branch_id;
            $data['department_id'] = null;
        }

        if ($role === 'department_head') {
            $data['scope'] = 'department';
            $data['department_id'] = $request->user()->department_id;
            $data['branch_id'] = null;
        }

        $publication = $this->forms->publish($form, $request->user(), $data);

        return response()->json($publication, 201);
    }

    public function submit(Request $request, CustomFormPublication $publication): JsonResponse
    {
        abort_unless($this->forms->accessiblePublication($request->user(), $publication), 403);

        $data = $request->validate([
            'payload' => ['required', 'array'],
        ]);

        return response()->json($this->forms->submit($publication, $request->user(), $data['payload']), 201);
    }

    public function submissions(Request $request, CustomForm $form): JsonResponse
    {
        return response()->json([
            'form' => $this->repository->templateWithSubmissionCount($form),
            'submissions' => $this->forms->submissionsFor($request->user(), $form),
        ]);
    }

    public function userHistory(Request $request, User $user): JsonResponse
    {
        return response()->json($this->forms->historyFor($request->user(), $user));
    }
}
