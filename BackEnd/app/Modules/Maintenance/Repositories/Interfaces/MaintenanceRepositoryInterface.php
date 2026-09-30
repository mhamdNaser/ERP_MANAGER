<?php

namespace App\Modules\Maintenance\Repositories\Interfaces;

use App\Models\MaintenanceBrand;
use App\Models\MaintenanceCategory;
use App\Models\MaintenanceImport;
use App\Models\MaintenanceItem;
use App\Models\MaintenanceType;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

interface MaintenanceRepositoryInterface
{
    public function items(array $filters): Collection;

    public function loadItem(MaintenanceItem $item): MaintenanceItem;

    public function createItem(array $attributes): MaintenanceItem;

    public function updateItem(MaintenanceItem $item, array $attributes): MaintenanceItem;

    public function deleteItem(MaintenanceItem $item): void;

    public function lockItem(int $id): MaintenanceItem;

    public function recordMovement(MaintenanceItem $item, array $attributes): void;

    public function findMatchingItem(string $name, ?string $partNumber, ?int $categoryId, ?int $exceptImportId = null): ?MaintenanceItem;

    public function catalog(): array;

    public function createCategory(array $attributes): MaintenanceCategory;

    public function createType(array $attributes): MaintenanceType;

    public function createBrand(array $attributes): MaintenanceBrand;

    public function categoryNamed(string $name): MaintenanceCategory;

    public function categoryIdNamed(string $name): ?int;

    public function typeNamesOf(int $categoryId): array;

    public function typeNamed(int $categoryId, string $name): MaintenanceType;

    public function brandNamed(string $name): MaintenanceBrand;

    public function movementsSince(CarbonInterface $since): Collection;

    public function recentMovements(int $limit): Collection;

    public function imports(): Collection;

    public function createImport(array $attributes): MaintenanceImport;

    public function deleteImport(MaintenanceImport $import): void;

    public function transaction(callable $callback): mixed;
}
