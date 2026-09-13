<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ncm_notifications') && ! Schema::hasTable('cnd_notifications')) {
            Schema::rename('ncm_notifications', 'cnd_notifications');
        }

        DB::table('users')->where('email', 'like', '%@ncm.local')->get(['id', 'email'])->each(
            fn ($user) => DB::table('users')->where('id', $user->id)->update([
                'email' => str_replace('@ncm.local', '@cnd.local', $user->email),
            ])
        );

        DB::table('users')->where('employee_number', 'like', 'NCM-%')->get(['id', 'employee_number'])->each(
            fn ($user) => DB::table('users')->where('id', $user->id)->update([
                'employee_number' => str_replace('NCM-', 'CND-', $user->employee_number),
            ])
        );
    }

    public function down(): void
    {
        if (Schema::hasTable('cnd_notifications') && ! Schema::hasTable('ncm_notifications')) {
            Schema::rename('cnd_notifications', 'ncm_notifications');
        }

        DB::table('users')->where('email', 'like', '%@cnd.local')->get(['id', 'email'])->each(
            fn ($user) => DB::table('users')->where('id', $user->id)->update([
                'email' => str_replace('@cnd.local', '@ncm.local', $user->email),
            ])
        );

        DB::table('users')->where('employee_number', 'like', 'CND-%')->get(['id', 'employee_number'])->each(
            fn ($user) => DB::table('users')->where('id', $user->id)->update([
                'employee_number' => str_replace('CND-', 'NCM-', $user->employee_number),
            ])
        );
    }
};
