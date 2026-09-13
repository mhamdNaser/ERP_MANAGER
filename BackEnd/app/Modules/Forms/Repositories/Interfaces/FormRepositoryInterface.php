<?php
namespace App\Modules\Forms\Repositories\Interfaces;

use App\Models\CustomForm;
use App\Models\CustomFormPublication;
use App\Models\CustomFormSubmission;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface FormRepositoryInterface
{
    public function activeTemplates(): Collection;
    public function publications(): Collection;
    public function loadTemplate(CustomForm $form): CustomForm;
    public function templateWithSubmissionCount(CustomForm $form): CustomForm;

    public function createTemplate(array $attributes): CustomForm;
    public function updateTemplate(CustomForm $form, array $attributes): CustomForm;
    public function deleteTemplate(CustomForm $form): void;
    /** يحذف حقول النموذج ثم يكتب الصفوف الممرَّرة بدلاً منها. */
    public function replaceFields(CustomForm $form, array $rows): void;

    public function createPublication(array $attributes): CustomFormPublication;
    public function loadPublication(CustomFormPublication $publication): CustomFormPublication;
    public function publicationIssuedBy(CustomForm $form, int $userId): bool;

    public function hasSubmitted(int $publicationId, int $userId): bool;
    public function nextSubmissionVersion(int $formId, int $userId): int;
    public function createSubmission(array $attributes): CustomFormSubmission;
    public function submissionsByUser(User $user): Collection;
    public function submissionsForForm(CustomForm $form): Collection;

    /** المعنيّون بالنشر: ['roles'، 'employment_type'، 'branch_id'، 'department_id'، 'except_id']. */
    public function recipients(array $criteria): Collection;

    public function transaction(callable $callback): mixed;
}
