<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * كل الطلبات صارت تُوجَّه إلى الموارد البشرية مباشرة، فتُنقل الطلبات
 * العالقة عند رئيس القسم إلى مرحلة الموارد البشرية.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('hr_requests')
            ->where('stage', 'head')
            ->update(['stage' => 'hr', 'status' => 'pending_hr']);
    }

    public function down(): void
    {
        // لا رجعة: مرحلة رئيس القسم لم تعد جزءاً من المسار.
    }
};
