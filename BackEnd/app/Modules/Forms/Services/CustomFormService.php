<?php

namespace App\Modules\Forms\Services;

use App\Models\CustomForm;
use App\Models\CustomFormPublication;
use App\Models\CustomFormSubmission;
use App\Models\User;
use App\Modules\Forms\Repositories\Interfaces\FormRepositoryInterface;
use App\Modules\Notifications\Repositories\Interfaces\NotificationRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;

class CustomFormService
{
    private const TARGET_GROUP_ALIASES = [
        'employees' => 'branch_managers_heads_employees',
        'managers' => 'branch_managers_heads',
    ];

    /** أدوار كل فئة مستهدفة كما تُستعمل في الإشعارات. */
    private const TARGET_GROUP_ROLES = [
        'branch_managers_heads_employees' => ['branch_manager', 'department_head', 'employee', 'technician'],
        'employees' => ['branch_manager', 'department_head', 'employee', 'technician'],
        'branch_managers_heads' => ['branch_manager', 'department_head'],
        'managers' => ['branch_manager', 'department_head'],
        'branch_managers' => ['branch_manager'],
        'fixed_employees' => ['employee', 'technician'],
        'contract_employees' => ['employee', 'technician'],
    ];

    public function __construct(
        private FormRepositoryInterface $forms,
        private NotificationRepositoryInterface $notifications,
    ) {}

    public function formsFor(User $user): array
    {
        $templates = in_array($user->primaryRole(), ['database_manager', 'general_manager', 'branch_manager', 'department_head'], true)
            ? $this->forms->activeTemplates()
            : collect();

        $publications = $this->forms->publications()
            ->filter(fn (CustomFormPublication $publication) => $this->publicationVisibleTo($user, $publication))
            ->values();

        $assigned = $publications->filter(
            fn (CustomFormPublication $publication) => $this->userMatchesPublication($user, $publication)
                && ! $this->forms->hasSubmitted($publication->id, $user->id),
        );

        return [$templates, $assigned, $this->forms->submissionsByUser($user)];
    }

    public function createTemplate(User $creator, array $payload): CustomForm
    {
        return $this->forms->transaction(function () use ($creator, $payload) {
            $form = $this->forms->createTemplate([
                'creator_id' => $creator->id,
                'title' => $payload['title'],
                'description' => $payload['description'] ?? null,
                'target_group' => $this->normalizeTargetGroup($payload['target_group'] ?? 'branch_managers_heads_employees'),
                'default_scope' => $payload['default_scope'] ?? 'organization',
                'default_duration_days' => $payload['default_duration_days'] ?? null,
                'is_active' => $payload['is_active'] ?? true,
            ]);

            $this->forms->replaceFields($form, $this->fieldRows($payload['fields'] ?? []));

            return $this->forms->loadTemplate($form);
        });
    }

    public function updateTemplate(CustomForm $form, array $payload): CustomForm
    {
        return $this->forms->transaction(function () use ($form, $payload) {
            $this->forms->updateTemplate($form, [
                'title' => $payload['title'],
                'description' => $payload['description'] ?? null,
                'target_group' => $this->normalizeTargetGroup($payload['target_group'] ?? $form->target_group),
                'default_scope' => $payload['default_scope'] ?? $form->default_scope,
                'default_duration_days' => $payload['default_duration_days'] ?? null,
                'is_active' => $payload['is_active'] ?? $form->is_active,
            ]);

            $this->forms->replaceFields($form, $this->fieldRows($payload['fields'] ?? []));

            return $this->forms->loadTemplate($form);
        });
    }

    public function deleteTemplate(CustomForm $form): void
    {
        $this->forms->deleteTemplate($form);
    }

    public function publish(CustomForm $form, User $issuer, array $payload): CustomFormPublication
    {
        return $this->forms->transaction(function () use ($form, $issuer, $payload) {
            $scope = $payload['scope'] ?? $form->default_scope;

            $publication = $this->forms->createPublication([
                'custom_form_id' => $form->id,
                'issuer_id' => $issuer->id,
                'scope' => $scope,
                'target_group' => $this->normalizeTargetGroup($payload['target_group'] ?? $form->target_group),
                'branch_id' => $scope === 'branch' ? ($payload['branch_id'] ?? $issuer->branch_id) : null,
                'department_id' => $scope === 'department' ? ($payload['department_id'] ?? $issuer->department_id) : null,
                'visible_from' => Arr::get($payload, 'visible_from'),
                'visible_until' => Arr::get($payload, 'visible_until') ?? (
                    Arr::get($payload, 'duration_days')
                        ? now()->addDays((int) Arr::get($payload, 'duration_days'))
                        : ($form->default_duration_days ? now()->addDays((int) $form->default_duration_days) : null)
                ),
                'status' => $payload['status'] ?? 'active',
                'message' => $payload['message'] ?? null,
            ]);

            $this->notifyRecipients($form, $publication, $issuer);

            return $this->forms->loadPublication($publication);
        });
    }

    public function submit(CustomFormPublication $publication, User $user, array $payload): CustomFormSubmission
    {
        return $this->forms->createSubmission([
            'custom_form_id' => $publication->custom_form_id,
            'custom_form_publication_id' => $publication->id,
            'user_id' => $user->id,
            'version' => $this->forms->nextSubmissionVersion($publication->custom_form_id, $user->id),
            'payload' => $payload,
            'submitted_at' => now(),
        ]);
    }

    public function publicationVisibleTo(User $user, CustomFormPublication $publication): bool
    {
        if (in_array($user->primaryRole(), ['database_manager', 'general_manager'], true)) {
            return true;
        }

        if ($publication->scope === 'organization') {
            return true;
        }

        if ($publication->scope === 'branch') {
            return $user->branch_id && (int) $user->branch_id === (int) $publication->branch_id;
        }

        if ($publication->scope === 'department') {
            return $user->department_id && (int) $user->department_id === (int) $publication->department_id;
        }

        return false;
    }

    public function userMatchesPublication(User $user, CustomFormPublication $publication): bool
    {
        if (!$this->publicationVisibleTo($user, $publication)) {
            return false;
        }

        return $this->matchesTargetGroup($user, $publication->target_group);
    }

    public function accessiblePublication(User $user, CustomFormPublication $publication): bool
    {
        if ($user->primaryRole() === 'database_manager') {
            return true;
        }

        if ($publication->issuer_id === $user->id) {
            return true;
        }

        if (in_array($user->primaryRole(), ['general_manager', 'branch_manager', 'department_head'], true)) {
            return $this->publicationVisibleTo($user, $publication);
        }

        return $this->userMatchesPublication($user, $publication);
    }

    public function accessibleTemplate(User $user, CustomForm $form): bool
    {
        if (in_array($user->primaryRole(), ['database_manager', 'general_manager'], true)) {
            return true;
        }

        return $form->creator_id === $user->id;
    }

    public function historyFor(User $viewer, User $target): Collection
    {
        abort_unless($this->canViewUserHistory($viewer, $target), 403);

        return $this->forms->submissionsByUser($target);
    }

    public function submissionsFor(User $viewer, CustomForm $form): Collection
    {
        abort_unless($this->canViewFormSubmissions($viewer, $form), 403);

        return $this->forms->submissionsForForm($form);
    }

    public function canViewUserHistory(User $viewer, User $target): bool
    {
        if ($viewer->id === $target->id) {
            return true;
        }

        if (in_array($viewer->primaryRole(), ['database_manager', 'general_manager'], true)) {
            return true;
        }

        if ($viewer->primaryRole() === 'branch_manager') {
            return $viewer->branch_id && $viewer->branch_id === $target->branch_id;
        }

        if ($viewer->primaryRole() === 'department_head') {
            return $viewer->department_id && $viewer->department_id === $target->department_id;
        }

        return false;
    }

    public function canViewFormSubmissions(User $viewer, CustomForm $form): bool
    {
        if (in_array($viewer->primaryRole(), ['database_manager', 'general_manager'], true)) {
            return true;
        }

        if ($form->creator_id === $viewer->id) {
            return true;
        }

        return $this->forms->publicationIssuedBy($form, $viewer->id);
    }

    private function normalizeTargetGroup(string $group): string
    {
        return self::TARGET_GROUP_ALIASES[$group] ?? $group;
    }

    private function matchesTargetGroup(User $user, string $group): bool
    {
        $group = $this->normalizeTargetGroup($group);
        $role = $user->primaryRole();
        $isStaff = in_array($role, ['employee', 'technician'], true);
        $isFixedStaff = $isStaff && $user->employment_type === 'fixed';
        $isContractStaff = $isStaff && $user->employment_type !== 'fixed';

        return match ($group) {
            'all_staff' => true,
            'branch_managers' => $role === 'branch_manager',
            'branch_managers_heads' => in_array($role, ['branch_manager', 'department_head'], true),
            'branch_managers_heads_employees' => in_array($role, ['branch_manager', 'department_head', 'employee', 'technician'], true),
            'fixed_employees' => $isFixedStaff,
            'contract_employees' => $isContractStaff,
            default => false,
        };
    }

    /** ترتيب الحقول ومفاتيحها يُشتقّان من موضعها في النموذج. */
    private function fieldRows(array $fields): array
    {
        return collect($fields)->values()->map(fn (array $field, int $index) => [
            'field_key' => $field['field_key'] ?? 'field_' . ($index + 1),
            'label' => $field['label'],
            'input_type' => $field['input_type'],
            'options' => $field['options'] ?? null,
            'placeholder' => $field['placeholder'] ?? null,
            'help_text' => $field['help_text'] ?? null,
            'is_required' => (bool) ($field['is_required'] ?? false),
            'sort_order' => $index,
        ])->all();
    }

    private function notifyRecipients(CustomForm $form, CustomFormPublication $publication, User $issuer): void
    {
        $recipients = $this->forms->recipients([
            'roles' => self::TARGET_GROUP_ROLES[$publication->target_group] ?? null,
            'employment_type' => match ($publication->target_group) {
                'fixed_employees' => 'fixed',
                'contract_employees' => 'contract',
                default => null,
            },
            'scope' => $publication->scope,
            'branch_id' => $publication->branch_id,
            'department_id' => $publication->department_id,
            'except_id' => $issuer->id,
        ]);

        foreach ($recipients as $recipient) {
            $this->notifications->create([
                'user_id' => $recipient->id,
                'title' => $form->title,
                'message' => $publication->message ?: __('messages.report_updated'),
                'custom_form_id' => $form->id,
                'custom_form_publication_id' => $publication->id,
            ]);
        }
    }
}
