<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * بحثٌ وترقيمٌ موحّدان لقوائم الشاشات.
 *
 * الترقيم في الخادم لا في المتصفح: القوائم تكبر (أفرع، أقسام، موظفون)
 * وجلبها كاملةً في كل فتحة للشاشة هدرٌ يزداد مع الزمن.
 */
class ListQuery
{
    /** خمسة عناصر في الصفحة — ما تعرضه الشاشة افتراضياً. */
    public const PER_PAGE = 5;

    private const MAX_PER_PAGE = 100;

    /**
     * يقيّد الاستعلام بكلمة بحث على الأعمدة المذكورة.
     *
     * العمود بصيغة «علاقة.عمود» يُبحث داخل العلاقة. والمقارنة بـLOWER لا
     * بـILIKE: الأولى تعمل على PostgreSQL وSQLite معاً (والاختبارات على
     * SQLite)، والثانية حكرٌ على PostgreSQL. العربية لا تتأثر بـLOWER،
     * والرموز اللاتينية (الأكواد والبريد) تصير غير حسّاسة لحالة الأحرف.
     */
    public static function search(Builder $query, ?string $term, array $columns): Builder
    {
        $term = trim((string) $term);
        if ($term === '' || $columns === []) {
            return $query;
        }

        $needle = '%' . mb_strtolower($term) . '%';

        return $query->where(function (Builder $scoped) use ($columns, $needle) {
            foreach ($columns as $column) {
                if (! str_contains($column, '.')) {
                    $scoped->orWhereRaw('LOWER(' . $column . ') LIKE ?', [$needle]);
                    continue;
                }

                [$relation, $related] = explode('.', $column, 2);
                $scoped->orWhereHas($relation, fn (Builder $inner) => $inner
                    ->whereRaw('LOWER(' . $related . ') LIKE ?', [$needle]));
            }
        });
    }

    /** عدد العناصر في الصفحة، مقيَّداً كي لا يطلب عميلٌ الجدول كاملاً. */
    public static function perPage(mixed $requested): int
    {
        $value = (int) $requested;

        if ($value < 1) {
            return self::PER_PAGE;
        }

        return min($value, self::MAX_PER_PAGE);
    }

    /** ما تحتاجه الواجهة لرسم أزرار التنقّل. */
    public static function meta(LengthAwarePaginator $page): array
    {
        return [
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'per_page' => $page->perPage(),
            'total' => $page->total(),
        ];
    }
}
