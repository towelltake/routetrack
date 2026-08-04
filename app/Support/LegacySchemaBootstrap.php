<?php

namespace App\Support;

use RuntimeException;
use Illuminate\Support\Facades\Schema;

class LegacySchemaBootstrap
{
    /**
     * @param  array<int, string>  $tables
     * @return array<string, string>
     */
    public static function loadStatements(array $tables): array
    {
        $snapshot = LegacySchemaSnapshot::TABLES;
        $missing = [];
        $found = [];

        foreach ($tables as $table) {
            if (isset($snapshot[$table])) {
                $found[$table] = $snapshot[$table];
                continue;
            }

            if (!Schema::hasTable($table)) {
                $missing[] = $table;
            }
        }

        if ($missing !== []) {
            throw new RuntimeException(
                'The following required legacy tables are missing from the embedded snapshot and the target database: '
                . implode(', ', $missing)
                . '. Regenerate the embedded schema snapshot from the reference SQL or provision the missing tables manually.'
            );
        }

        return $found;
    }
}
