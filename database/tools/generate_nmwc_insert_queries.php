<?php

declare(strict_types=1);

use App\Support\SqlStatementFileReader;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$database = (string) config('database.connections.' . config('database.default') . '.database');
$prefix = (string) config('database.connections.' . config('database.default') . '.prefix', '');
$sourceDatabase = $argv[1] ?? 'sfa_nmwc';
$legacySqlPath = realpath(__DIR__ . '/../../nmwc/nmwc_aswin_structure.sql');
$outputPath = __DIR__ . '/../../tracv2_nmwc_insert_queries.txt';

if ($legacySqlPath === false) {
    fwrite(STDERR, "Unable to locate legacy SQL dump.\n");
    exit(1);
}

$legacyTables = loadLegacyTableColumns($legacySqlPath);
$targetTables = loadTargetTables($database, $prefix);
$targetColumns = loadTargetColumns($database, $targetTables);

$lines = [];
$lines[] = '-- Generated from nmwc/nmwc_aswin_structure.sql against database ' . $database;
$lines[] = '-- Source database for data copy: ' . $sourceDatabase;
$lines[] = '-- Covers all live tables with prefix ' . $prefix;
$lines[] = '--';

foreach ($targetTables as $targetTable) {
    $baseTable = str_starts_with($targetTable, $prefix) ? substr($targetTable, strlen($prefix)) : $targetTable;
    $lines[] = '-- ' . $targetTable;

    if (! isset($legacyTables[$baseTable])) {
        $lines[] = '-- SKIP: no legacy source table `' . $baseTable . '` found in nmwc_aswin_structure.sql';
        $lines[] = '';
        continue;
    }

    $sharedColumns = array_values(array_intersect($targetColumns[$targetTable], $legacyTables[$baseTable]));

    if ($sharedColumns === []) {
        $lines[] = '-- SKIP: legacy table `' . $baseTable . '` exists but has no shared columns with `' . $targetTable . '`';
        $lines[] = '';
        continue;
    }

    $quotedColumns = implode(', ', array_map(
        static fn (string $column): string => '`' . str_replace('`', '``', $column) . '`',
        $sharedColumns
    ));

    $lines[] = 'INSERT INTO `' . $targetTable . '` (' . $quotedColumns . ')';
    $lines[] = 'SELECT ' . $quotedColumns;
    $lines[] = 'FROM `' . $sourceDatabase . '`.`' . $baseTable . '`;';
    $lines[] = '';
}

file_put_contents($outputPath, implode(PHP_EOL, $lines));

fwrite(STDOUT, 'Generated ' . count($targetTables) . ' table entries at ' . $outputPath . PHP_EOL);

/**
 * @return array<string, list<string>>
 */
function loadLegacyTableColumns(string $path): array
{
    $tables = [];

    foreach (SqlStatementFileReader::statements($path) as $statement) {
        if (preg_match('/^CREATE TABLE `([^`]+)`/i', $statement, $matches) !== 1) {
            continue;
        }

        $table = $matches[1];
        preg_match_all('/^\s*`([^`]+)`/m', $statement, $columnMatches);
        $tables[$table] = array_values(array_unique($columnMatches[1]));
    }

    return $tables;
}

/**
 * @return list<string>
 */
function loadTargetTables(string $database, string $prefix): array
{
    $rows = DB::select(
        'SELECT TABLE_NAME AS table_name
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = ?
           AND TABLE_NAME LIKE ?
         ORDER BY TABLE_NAME',
        [$database, $prefix . '%']
    );

    return array_map(
        static function (object $row): string {
            $values = get_object_vars($row);
            return (string) reset($values);
        },
        $rows
    );
}

/**
 * @param  list<string>  $tables
 * @return array<string, list<string>>
 */
function loadTargetColumns(string $database, array $tables): array
{
    if ($tables === []) {
        return [];
    }

    $placeholders = implode(', ', array_fill(0, count($tables), '?'));
    $rows = DB::select(
        'SELECT TABLE_NAME AS table_name, COLUMN_NAME AS column_name
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = ?
           AND TABLE_NAME IN (' . $placeholders . ')
         ORDER BY TABLE_NAME, ORDINAL_POSITION',
        array_merge([$database], $tables)
    );

    $columns = [];

    foreach ($rows as $row) {
        $table = (string) $row->table_name;
        $columns[$table] ??= [];
        $columns[$table][] = (string) $row->column_name;
    }

    return $columns;
}
