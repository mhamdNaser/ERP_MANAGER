<?php
namespace App\Modules\Offices\Repositories\Eloquent;

use App\Models\Office;
use App\Modules\Offices\Repositories\Interfaces\OfficeRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class OfficeRepository implements OfficeRepositoryInterface
{
    public function all(): Collection { return Office::withCount('users')->orderBy('name')->get(); }
    public function create(array $data): Office { return Office::create($data); }
    public function update(Office $office, array $data): Office { $office->update($data); return $office; }
    public function delete(Office $office): bool { return (bool) $office->delete(); }
}
