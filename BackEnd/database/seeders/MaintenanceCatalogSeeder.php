<?php

namespace Database\Seeders;

use App\Models\MaintenanceBrand;
use App\Models\MaintenanceCategory;
use App\Models\MaintenanceType;
use Illuminate\Database\Seeder;

/**
 * قوائم البداية لقسم الصيانة، مستخرجة من ملفَي الفرع: قطع إلكترونية
 * (ملف Elements) وعدد وأدوات وأجهزة قياس (ملف التجهيزات). لا يضيف قطعاً
 * ولا كميات، ويمكن تشغيله أكثر من مرة دون تكرار.
 */
class MaintenanceCatalogSeeder extends Seeder
{
    private const CATALOG = [
        'قطع إلكترونية' => [
            'description' => 'عناصر تُركَّب في الأجهزة: دارات متكاملة، ترانزستورات، شاشات...',
            'types' => ['IC', 'ترانزستور', 'ذاكرة', 'شاشة', 'محوّل إيثرنت', 'مفتاح ترميز دوّار', 'كابل ووصلات'],
        ],
        'عدد وأدوات يدوية' => [
            'description' => 'أدوات طاولة الصيانة ومستهلكاتها.',
            'types' => ['مفكات', 'زرادية وقطاعة', 'ملاقط', 'كاوي ورؤوسه', 'مستهلكات لحام', 'مواد تنظيف', 'تخزين وتنظيم'],
        ],
        'أجهزة قياس ومعدات' => [
            'description' => 'أجهزة الفحص والقياس والمعدات الكبيرة.',
            'types' => ['راسم إشارة', 'محلل طيف', 'مجهر', 'مزوّد طاقة', 'محطة هواء ساخن', 'مولد نبضات', 'ماكينة لحام نقطي', 'طابعة ثلاثية الأبعاد'],
        ],
    ];

    private const BRANDS = ['goot', 'YAXUN', 'SUGON', 'RIGOL', 'YCS', 'Mechanic', 'Hytera', 'UNI-T', '2UUL'];

    public function run(): void
    {
        foreach (self::CATALOG as $name => $definition) {
            $category = MaintenanceCategory::firstOrCreate(['name' => $name], ['description' => $definition['description']]);

            foreach ($definition['types'] as $type) {
                MaintenanceType::firstOrCreate(['category_id' => $category->id, 'name' => $type]);
            }
        }

        foreach (self::BRANDS as $brand) {
            MaintenanceBrand::firstOrCreate(['name' => $brand]);
        }
    }
}
