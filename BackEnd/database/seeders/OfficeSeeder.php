<?php

namespace Database\Seeders;

use App\Models\Office;
use App\Models\User;
use Illuminate\Database\Seeder;

class OfficeSeeder extends Seeder
{
    public function run(): void
    {
        $registry = Office::firstOrCreate(['code' => 'REGISTRY'], ['name' => 'الديوان']);
        Office::firstOrCreate(['code' => 'PERSONNEL'], ['name' => 'الذاتية']);
        Office::firstOrCreate(['code' => 'CLERICAL'], ['name' => 'القلم']);
        $user = User::firstOrCreate(['email' => 'registry@cnd.local'], ['name' => 'أمين الديوان', 'password' => 'password', 'role' => 'employee', 'job_title' => 'أمين الديوان', 'employee_number' => 'CND-0200', 'office_id' => $registry->id]);
        $user->update(['office_id' => $registry->id, 'branch_id' => null, 'department_id' => null]);
        $user->syncRoles(['employee']);
    }
}
