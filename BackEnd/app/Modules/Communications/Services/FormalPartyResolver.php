<?php

namespace App\Modules\Communications\Services;

use App\Models\ExternalEntity;
use App\Models\FormalCorrespondence;
use App\Modules\Communications\Repositories\Interfaces\FormalCorrespondenceRepositoryInterface;

/**
 * يوحّد تسمية الأطراف: الجهة المصدرة والجهة المخاطبة، داخلية كانت أم خارجية.
 */
class FormalPartyResolver
{
    public function __construct(private FormalCorrespondenceRepositoryInterface $formal) {}

    public const OUR_ORG_LABEL = 'مؤسستنا';

    public const INTERNAL_TYPES = ['branch', 'department', 'office', 'general_manager', 'diwan'];

    /** أنواع الجهات المسموح بها لكل اتجاه مراسلة. */
    public const DIRECTION_RULES = [
        'external_to_internal' => [
            'source' => ['external_entity'],
            'target' => ['our_org'],
        ],
        'internal_to_external' => [
            'source' => ['branch', 'department', 'office', 'general_manager', 'diwan'],
            'target' => ['external_entity'],
        ],
        'internal_to_internal' => [
            'source' => ['branch', 'department', 'office', 'general_manager', 'diwan'],
            'target' => ['branch', 'department', 'office', 'general_manager', 'diwan'],
        ],
    ];

    /** يحوّل (نوع، معرّف، نص احتياطي) إلى جهة موحّدة بتسمية عربية. */
    public function resolve(?string $type, $id, ?string $fallback = null): array
    {
        $type = $type ?: 'free_text';
        $id = $id !== null && $id !== '' ? (int) $id : null;
        $label = $fallback ?: 'غير محددة';

        return match ($type) {
            'our_org' => ['type' => 'our_org', 'id' => null, 'label' => self::OUR_ORG_LABEL],
            'diwan' => ['type' => 'diwan', 'id' => null, 'label' => 'الديوان'],
            'general_manager' => ['type' => 'general_manager', 'id' => $id, 'label' => $this->nameOf($type, $id) ?: 'المدير العام'],
            'branch' => $id ? ['type' => 'branch', 'id' => $id, 'label' => $this->nameOf($type, $id) ?: $label] : ['type' => 'branch', 'id' => null, 'label' => $label],
            'department' => $id ? ['type' => 'department', 'id' => $id, 'label' => $this->nameOf($type, $id) ?: $label] : ['type' => 'department', 'id' => null, 'label' => $label],
            'office' => $id ? ['type' => 'office', 'id' => $id, 'label' => $this->nameOf($type, $id) ?: $label] : ['type' => 'office', 'id' => null, 'label' => $label],
            'external_entity' => ['type' => 'external_entity', 'id' => $id, 'label' => $this->nameOf($type, $id) ?: $label],
            'user' => ['type' => 'user', 'id' => $id, 'label' => $this->nameOf($type, $id) ?: $label],
            default => ['type' => $type, 'id' => $id, 'label' => $label],
        };
    }

    /** يحدد الجهة المصدرة والمخاطبة انطلاقاً من اتجاه المراسلة ومدخلات النموذج. */
    public function partiesForDirection(string $direction, array $data): array
    {
        $rules = self::DIRECTION_RULES[$direction] ?? self::DIRECTION_RULES['internal_to_internal'];

        $sourceType = in_array($data['source_type'] ?? null, $rules['source'], true)
            ? $data['source_type']
            : $rules['source'][0];
        $targetType = in_array($data['target_type'] ?? null, $rules['target'], true)
            ? $data['target_type']
            : $rules['target'][0];

        $sourceId = $data['source_id'] ?? null;
        if ($sourceType === 'external_entity' && ! $sourceId && ! empty($data['source_name'])) {
            $sourceId = $this->externalEntityFromName($data['source_name'])?->id;
        }

        $targetId = $data['target_id'] ?? null;
        if ($targetType === 'external_entity' && ! $targetId && ! empty($data['target_name'])) {
            $targetId = $this->externalEntityFromName($data['target_name'])?->id;
        }

        return [
            'source' => $this->resolve($sourceType, $sourceId, $data['source_name'] ?? null),
            'target' => $this->resolve($targetType, $targetId, $data['target_name'] ?? null),
        ];
    }

    public function externalEntityFromName(?string $name): ?ExternalEntity
    {
        $name = trim((string) $name);

        return $name === '' ? null : $this->formal->externalEntityByName($name);
    }

    /** اسم الجهة من القاعدة، أو null إن لم يُمرَّر معرّف. */
    private function nameOf(string $type, ?int $id): ?string
    {
        return $id ? $this->formal->partyName($type, $id) : null;
    }

    /** الأعمدة القديمة تبقى محدّثة كي لا تنكسر التقارير والواجهات السابقة. */
    public function legacyColumns(string $direction, array $source, array $target): array
    {
        return [
            'sender_external_entity_id' => $source['type'] === 'external_entity' ? $source['id'] : null,
            'recipient_external_entity_id' => $target['type'] === 'external_entity' ? $target['id'] : null,
            'recipient_branch_id' => $target['type'] === 'branch' ? $target['id'] : null,
            'recipient_department_id' => $target['type'] === 'department' ? $target['id'] : null,
        ];
    }

    public function defaultActionRequired(?string $targetType): string
    {
        return match ($targetType) {
            'general_manager' => 'decision',
            'branch', 'department', 'office' => 'study',
            'external_entity' => 'reply',
            default => 'route',
        };
    }

    public function originLabel(FormalCorrespondence $item): string
    {
        return $item->source_label
            ?: match ($item->direction) {
                'internal_to_external', 'internal_to_internal' => self::OUR_ORG_LABEL,
                default => $item->senderExternalEntity?->name ?: 'جهة خارجية',
            };
    }

    /** الجهة التي تحتفظ بالمراسلة حالياً؛ تصبح الجهة المصدرة للمعالجة التالية. */
    public function currentHolder(FormalCorrespondence $item): array
    {
        $latest = $this->formal->latestEvent($item);

        if (! $latest) {
            return ['type' => $item->source_type, 'id' => $item->source_id, 'label' => $this->originLabel($item)];
        }

        return [
            'type' => $latest->target_type,
            'id' => $latest->target_id ? (int) $latest->target_id : null,
            'label' => $latest->target_label ?: ($latest->meta['place'] ?? $this->originLabel($item)),
        ];
    }

    public function knownPlaces(): array
    {
        return $this->formal->knownPlaceLabels();
    }
}
