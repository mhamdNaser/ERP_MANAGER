<?php
namespace App\Modules\Offices\Repositories\Interfaces;

use App\Models\Office;
use Illuminate\Database\Eloquent\Collection;

interface OfficeRepositoryInterface
{
    public function all(): Collection;
    public function create(array $data): Office;
    public function update(Office $office, array $data): Office;
    public function delete(Office $office): bool;
}
