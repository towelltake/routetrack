<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $database = DB::getDatabaseName();

        $tables = collect(DB::select(
            "
                SELECT DISTINCT t.TABLE_NAME
                FROM information_schema.TABLES t
                LEFT JOIN information_schema.COLUMNS c
                    ON c.TABLE_SCHEMA = t.TABLE_SCHEMA
                    AND c.TABLE_NAME = t.TABLE_NAME
                    AND c.CHARACTER_SET_NAME = 'latin1'
                WHERE t.TABLE_SCHEMA = ?
                    AND t.TABLE_TYPE = 'BASE TABLE'
                    AND (
                        t.TABLE_COLLATION LIKE 'latin1%'
                        OR c.TABLE_NAME IS NOT NULL
                    )
                ORDER BY t.TABLE_NAME
            ",
            [$database]
        ))->pluck('TABLE_NAME');

        if ($tables->isEmpty()) {
            return;
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            foreach ($tables as $table) {
                DB::statement(sprintf(
                    'ALTER TABLE `%s` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
                    str_replace('`', '``', $table)
                ));
            }
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    public function down(): void
    {
        // No automatic rollback: converting multilingual utf8mb4 data back to latin1 is lossy.
    }
};
