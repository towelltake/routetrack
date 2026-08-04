<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AmountPrecision
{
    private static ?int $cached = null;

    public static function get(): int
    {
        if (self::$cached !== null) {
            return self::$cached;
        }

        if (! Schema::hasTable('setup')) {
            return self::$cached = 3;
        }

        $value = DB::table('setup')->orderBy('setupid')->value('decimalplaces');

        return self::$cached = self::normalize($value);
    }

    public static function format(mixed $value, ?int $precision = null): string
    {
        return number_format((float) ($value ?? 0), $precision ?? self::get(), '.', '');
    }

    public static function normalize(mixed $value): int
    {
        $precision = is_numeric($value) ? (int) $value : 3;

        return max(0, min(6, $precision));
    }
}
