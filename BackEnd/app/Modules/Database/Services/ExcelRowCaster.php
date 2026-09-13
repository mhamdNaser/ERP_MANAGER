<?php

namespace App\Modules\Database\Services;

/** Casts a raw spreadsheet cell value to the PHP type a given Postgres column expects. */
class ExcelRowCaster
{
    private const INTEGER_TYPES = ['int2', 'int4', 'int8', 'smallint', 'integer', 'bigint'];

    private const FLOAT_TYPES = ['float4', 'float8', 'numeric', 'decimal', 'real', 'double precision'];

    private const BOOL_TYPES = ['bool', 'boolean'];

    private const JSON_TYPES = ['json', 'jsonb'];

    /**
     * @param  array{name: string, type_name: string, nullable: bool}  $column
     * @return array{0: mixed, 1: ?string} [castValue, errorMessage]
     */
    public function cast(mixed $raw, array $column): array
    {
        $trimmed = is_string($raw) ? trim($raw) : $raw;

        if ($trimmed === null || $trimmed === '') {
            return $column['nullable'] ? [null, null] : [null, "الحقل \"{$column['name']}\" مطلوب."];
        }

        $type = strtolower($column['type_name']);

        if (in_array($type, self::INTEGER_TYPES, true)) {
            return is_numeric($trimmed) && (float) $trimmed == (int) $trimmed
                ? [(int) $trimmed, null]
                : [null, "قيمة غير صحيحة لعمود رقمي صحيح \"{$column['name']}\": {$trimmed}"];
        }

        if (in_array($type, self::FLOAT_TYPES, true)) {
            return is_numeric($trimmed) ? [(float) $trimmed, null] : [null, "قيمة غير صحيحة لعمود عشري \"{$column['name']}\": {$trimmed}"];
        }

        if (in_array($type, self::BOOL_TYPES, true)) {
            $normalized = mb_strtolower((string) $trimmed);
            if (in_array($normalized, ['1', 'true', 'نعم', 'yes'], true)) {
                return [true, null];
            }
            if (in_array($normalized, ['0', 'false', 'لا', 'no'], true)) {
                return [false, null];
            }

            return [null, "قيمة غير صحيحة لعمود منطقي \"{$column['name']}\": {$trimmed}"];
        }

        if (in_array($type, self::JSON_TYPES, true)) {
            json_decode((string) $trimmed);

            return json_last_error() === JSON_ERROR_NONE
                ? [(string) $trimmed, null]
                : [null, "قيمة JSON غير صحيحة بعمود \"{$column['name']}\"."];
        }

        return [(string) $trimmed, null];
    }
}
