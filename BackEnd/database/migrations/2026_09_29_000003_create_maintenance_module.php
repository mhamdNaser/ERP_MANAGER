<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * قسم الصيانة: مستودع القطع والعدد وحالاتها. بنية الصنف مأخوذة من ملفات
 * الفرع نفسها (اسم العنصر، السيريال، الجهاز، الوحدة، سعر الوحدة، العدد).
 *
 * كمية الصنف موزّعة على خمس حالات في أعمدة qty_*، ولا تتغير إلا بحركة
 * مسجّلة في maintenance_movements — فالسجل يفسّر كل رقم في الجدول.
 */
return new class extends Migration
{
    public const PERMISSIONS = [
        // تُمنح لمنتسبي فرع الصيانة مباشرةً، والأدوار تحمل الاطلاع وحده.
        'maintenance.view' => ['general_manager', 'database_manager'],
        'maintenance.manage' => ['database_manager'],
    ];

    public function up(): void
    {
        Schema::create('maintenance_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('maintenance_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('maintenance_categories')->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
            $table->unique(['category_id', 'name']);
        });

        Schema::create('maintenance_brands', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('maintenance_items', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable()->unique();
            $table->string('name');
            $table->string('part_number')->nullable()->index();   // السيريال / رقم القطعة / الموديل
            $table->foreignId('category_id')->nullable()->constrained('maintenance_categories')->nullOnDelete();
            $table->foreignId('type_id')->nullable()->constrained('maintenance_types')->nullOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained('maintenance_brands')->nullOnDelete();
            $table->string('device')->nullable();                  // الجهاز الذي تخدمه القطعة
            $table->string('unit')->default('قطعة');
            $table->decimal('unit_price', 12, 2)->nullable();      // بالدولار كما في ملفات الفرع
            $table->unsignedInteger('min_quantity')->default(0);   // تنبيه نقص المخزون
            $table->string('location')->nullable();                // الرف أو الدرج
            $table->text('notes')->nullable();
            $table->string('image_path')->nullable();
            $table->unsignedInteger('qty_in_stock')->default(0);
            $table->unsignedInteger('qty_under_maintenance')->default(0);
            $table->unsignedInteger('qty_damaged')->default(0);
            $table->unsignedInteger('qty_ready')->default(0);
            $table->unsignedInteger('qty_repaired')->default(0);
            $table->foreignId('import_id')->nullable()->index();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('maintenance_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('maintenance_items')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kind')->index();                      // receive | move | issue
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();
            $table->unsignedInteger('quantity');
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index('created_at');
        });

        // الملفات الأصلية المرفوعة تبقى محفوظة لتنزيلها لاحقاً كما رُفعت.
        Schema::create('maintenance_imports', function (Blueprint $table) {
            $table->id();
            $table->string('file_name');
            $table->string('file_path');
            $table->foreignId('category_id')->nullable()->constrained('maintenance_categories')->nullOnDelete();
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('merged_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $this->grantPermissions();
    }

    public function down(): void
    {
        Permission::whereIn('name', array_keys(self::PERMISSIONS))->delete();
        Schema::dropIfExists('maintenance_imports');
        Schema::dropIfExists('maintenance_movements');
        Schema::dropIfExists('maintenance_items');
        Schema::dropIfExists('maintenance_brands');
        Schema::dropIfExists('maintenance_types');
        Schema::dropIfExists('maintenance_categories');
    }

    private function grantPermissions(): void
    {
        foreach (self::PERMISSIONS as $name => $roles) {
            $permission = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);

            foreach ($roles as $roleName) {
                Role::where('name', $roleName)->where('guard_name', 'web')->first()?->givePermissionTo($permission);
            }
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
