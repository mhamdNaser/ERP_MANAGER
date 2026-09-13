<?php
namespace App\Modules\Forms\Repositories\Eloquent;

use App\Models\CustomForm;
use App\Models\CustomFormPublication;
use App\Models\CustomFormSubmission;
use App\Models\User;
use App\Modules\Forms\Repositories\Interfaces\FormRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class FormRepository implements FormRepositoryInterface
{
    private const TEMPLATE_RELATIONS = ['creator:id,name,job_title', 'fields', 'latestPublication.issuer:id,name,job_title'];

    private const PUBLICATION_RELATIONS = ['form.fields', 'issuer:id,name,job_title', 'branch:id,name', 'department:id,name'];

    private const SUBMISSION_RELATIONS = [
        'form.fields',
        'publication.issuer:id,name,job_title',
        'publication.branch:id,name',
        'publication.department:id,name',
        'user:id,name,job_title',
    ];

    public function activeTemplates(): Collection
    {
        return CustomForm::with(self::TEMPLATE_RELATIONS)
            ->withCount(['submissions as submission_count'])
            ->where('is_active', true)
            ->latest()
            ->get();
    }

    public function publications(): Collection
    {
        return CustomFormPublication::with(self::PUBLICATION_RELATIONS)->latest()->get();
    }

    public function loadTemplate(CustomForm $form): CustomForm
    {
        return $form->load(['creator:id,name,job_title', 'fields']);
    }

    public function templateWithSubmissionCount(CustomForm $form): CustomForm
    {
        return $form->load(self::TEMPLATE_RELATIONS)->loadCount(['submissions as submission_count']);
    }

    public function createTemplate(array $attributes): CustomForm
    {
        return CustomForm::create($attributes);
    }

    public function updateTemplate(CustomForm $form, array $attributes): CustomForm
    {
        $form->update($attributes);

        return $form;
    }

    public function deleteTemplate(CustomForm $form): void
    {
        $form->delete();
    }

    public function replaceFields(CustomForm $form, array $rows): void
    {
        $form->fields()->delete();

        foreach ($rows as $row) {
            $form->fields()->create($row);
        }
    }

    public function createPublication(array $attributes): CustomFormPublication
    {
        return CustomFormPublication::create($attributes);
    }

    public function loadPublication(CustomFormPublication $publication): CustomFormPublication
    {
        return $publication->load(self::PUBLICATION_RELATIONS);
    }

    public function publicationIssuedBy(CustomForm $form, int $userId): bool
    {
        return $form->publications()->where('issuer_id', $userId)->exists();
    }

    public function hasSubmitted(int $publicationId, int $userId): bool
    {
        return CustomFormSubmission::where('custom_form_publication_id', $publicationId)
            ->where('user_id', $userId)
            ->exists();
    }

    public function nextSubmissionVersion(int $formId, int $userId): int
    {
        return (int) (CustomFormSubmission::where('custom_form_id', $formId)->where('user_id', $userId)->max('version') ?? 0) + 1;
    }

    public function createSubmission(array $attributes): CustomFormSubmission
    {
        return CustomFormSubmission::create($attributes)->load(self::SUBMISSION_RELATIONS);
    }

    public function submissionsByUser(User $user): Collection
    {
        return CustomFormSubmission::with(self::SUBMISSION_RELATIONS)
            ->where('user_id', $user->id)
            ->latest('submitted_at')
            ->get();
    }

    public function submissionsForForm(CustomForm $form): Collection
    {
        return CustomFormSubmission::with([
            ...self::SUBMISSION_RELATIONS,
            'user:id,name,job_title,branch_id,department_id',
            'user.branch:id,name',
            'user.department:id,name',
        ])
            ->where('custom_form_id', $form->id)
            ->latest('submitted_at')
            ->get();
    }

    public function recipients(array $criteria): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->whereKeyNot($criteria['except_id'])
            ->when($criteria['roles'] ?? null, fn (Builder $query, array $roles) => $query->whereIn('role', $roles))
            ->when($criteria['employment_type'] ?? null, fn (Builder $query, string $type) => $query->where('employment_type', $type))
            // نطاق الفرع/القسم يُطبَّق بحسب نطاق النشر نفسه، لا بحسب امتلاء المعرّف.
            ->when(($criteria['scope'] ?? null) === 'branch', fn (Builder $query) => $query->where('branch_id', $criteria['branch_id'] ?? null))
            ->when(($criteria['scope'] ?? null) === 'department', fn (Builder $query) => $query->where('department_id', $criteria['department_id'] ?? null))
            ->get();
    }

    public function transaction(callable $callback): mixed
    {
        return DB::transaction($callback);
    }
}
