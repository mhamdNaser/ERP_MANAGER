<?php

namespace App\Modules\Permissions\Services;

use App\Models\TabAccessGrant;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * جمهور تبويبات الشريط الجانبي.
 *
 * تبويب بلا روابط يعمل كما هو حسب الأدوار. تبويب له روابط صار مقصوراً على
 * جمهوره (الجميع، فرع، قسم، موظف): من فيه يُمنح صلاحيات التبويب، ومن خارجه
 * يُحجب عنه التبويب وتُحجب صلاحياته عبر Gate::before — إلا أصحاب
 * always_visible_roles فلا يُحجب عنهم شيء.
 */
class TabAccessService
{
    /** @var array<int, array{granted: array, denied: array, visibility: array}> ذاكرة الطلب الواحد. */
    private array $resolved = [];

    private ?Collection $grants = null;

    public static function tabs(): array
    {
        return config('tab_access.tabs', []);
    }

    public static function permissionsFor(string $tab, string $level): array
    {
        $definition = self::tabs()[$tab] ?? null;
        if (! $definition || ! empty($definition['role'])) {
            return [];
        }

        return array_values(array_unique([
            ...($definition['view'] ?? []),
            ...($level === 'manage' ? ($definition['manage'] ?? []) : []),
        ]));
    }

    public function grantedPermissions(?User $user): array
    {
        return $this->resolve($user)['granted'];
    }

    public function deniedPermissions(?User $user): array
    {
        return $this->resolve($user)['denied'];
    }

    /** للتبويبات المقصورة فقط: {tab: يراه أم لا}. غيرها يبقى لحكم الأدوار. */
    public function visibility(?User $user): array
    {
        return $this->resolve($user)['visibility'];
    }

    /** true يمنح، false يحجب، null يترك القرار لباقي الفحوص. */
    public function decide(?User $user, string $ability): ?bool
    {
        $resolved = $this->resolve($user);

        return match (true) {
            in_array($ability, $resolved['granted'], true) => true,
            in_array($ability, $resolved['denied'], true) => false,
            default => null,
        };
    }

    public function forget(): void
    {
        $this->resolved = [];
        $this->grants = null;
    }

    private function resolve(?User $user): array
    {
        if (! $user || ! $user->is_active) {
            return ['granted' => [], 'denied' => [], 'visibility' => []];
        }

        return $this->resolved[$user->id] ??= $this->compute($user);
    }

    private function compute(User $user): array
    {
        $byTab = $this->allGrants()->groupBy('tab');
        $alwaysVisible = in_array($user->role, config('tab_access.always_visible_roles', []), true);
        $granted = [];
        $outside = [];
        $visibility = [];

        foreach ($byTab as $tab => $grants) {
            if (! isset(self::tabs()[$tab])) {
                continue;
            }
            $mine = $grants->filter(fn (TabAccessGrant $grant) => $this->matches($grant, $user));
            $visibility[$tab] = $alwaysVisible || $mine->isNotEmpty();

            if ($mine->isNotEmpty()) {
                $level = $mine->contains('level', 'manage') ? 'manage' : 'view';
                array_push($granted, ...self::permissionsFor($tab, $level));
            } elseif (! $alwaysVisible) {
                array_push($outside, ...self::permissionsFor($tab, 'manage'));
            }
        }

        $granted = array_values(array_unique($granted));

        return [
            'granted' => $granted,
            // صلاحية يمنحها تبويب آخر من جمهوره لا تُحجب بسبب هذا.
            'denied' => array_values(array_diff(array_unique($outside), $granted)),
            'visibility' => $visibility,
        ];
    }

    private function matches(TabAccessGrant $grant, User $user): bool
    {
        return $grant->everyone
            || ($grant->user_id && (int) $grant->user_id === (int) $user->id)
            || ($grant->branch_id && (int) $grant->branch_id === (int) $user->branch_id)
            || ($grant->department_id && (int) $grant->department_id === (int) $user->department_id);
    }

    /** الروابط قليلة، فتُقرأ مرة واحدة لكل طلب وتُطابَق في الذاكرة. */
    private function allGrants(): Collection
    {
        return $this->grants ??= TabAccessGrant::query()->get(['tab', 'everyone', 'branch_id', 'department_id', 'user_id', 'level']);
    }
}
