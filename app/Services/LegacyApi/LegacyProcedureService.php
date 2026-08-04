<?php

namespace App\Services\LegacyApi;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class LegacyProcedureService
{
    private array $resolvedTables = [];
    private array $discoveredTables = [];

    public function normalizeNulls(array $payload): array
    {
        array_walk_recursive($payload, function (&$value): void {
            if ($value === null || $value === 'null') {
                $value = '';
            }
        });

        return $payload;
    }

    public function table(string $table): Builder
    {
        return DB::query()->from(DB::raw($this->resolveTable($table)));
    }

    public function hasTable(string $table): bool
    {
        return $this->tableExists($table)
            || $this->tableExists($this->prefixedTable($table))
            || $this->discoverTable($table) !== null;
    }

    public function resolveTable(string $table): string
    {
        return $this->resolvedTables[$table] ??= $this->detectTable($table);
    }

    private function detectTable(string $table): string
    {
        $prefixed = $this->prefixedTable($table);

        if ($this->tableExists($table)) {
            return $table;
        }

        if ($this->tableExists($prefixed)) {
            return $prefixed;
        }

        if ($discovered = $this->discoverTable($table)) {
            return $discovered;
        }

        return $table;
    }

    private function prefixedTable(string $table): string
    {
        return (string) config('database.connections.mysql.prefix', '') . $table;
    }

    private function discoverTable(string $table): ?string
    {
        if (array_key_exists($table, $this->discoveredTables)) {
            return $this->discoveredTables[$table];
        }

        $database = DB::getDatabaseName();
        $result = DB::selectOne(
            'SELECT table_name
             FROM information_schema.tables
             WHERE table_schema = ?
               AND table_name REGEXP ?
             ORDER BY CASE
                 WHEN table_name = ? THEN 0
                 WHEN table_name LIKE ? THEN 1
                 ELSE 2
             END,
             LENGTH(table_name)
             LIMIT 1',
            [
                $database,
                '(^|_)' . preg_quote($table, '/') . '$',
                $table,
                '%_' . $table,
            ]
        );

        return $this->discoveredTables[$table] = $result->table_name ?? null;
    }

    private function tableExists(string $table): bool
    {
        if ($table === '') {
            return false;
        }

        $database = DB::getDatabaseName();
        $result = DB::selectOne(
            'SELECT EXISTS(
                SELECT 1
                FROM information_schema.tables
                WHERE table_schema = ?
                  AND table_name = ?
            ) AS table_exists',
            [$database, $table]
        );

        return (bool) ($result->table_exists ?? false);
    }
}
