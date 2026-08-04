<?php

use App\Support\SqlStatementFileReader;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = [
        'invoicerxddetail',
        'batchexpirydetail',
        'orderrxddetail',
        'promotiondetail',
        'customerpromotionplandetail',
        'surveyauditdetail',
        'posequipmentchangedetail',
        'nonservicedcustomer',
        'nosalesheader',
        't_access_override_log',
    ];

    public function up(): void
    {
        $statements = $this->findCreateStatements();

        Schema::disableForeignKeyConstraints();

        try {
            foreach (self::TABLES as $table) {
                if (Schema::hasTable($table)) {
                    continue;
                }

                DB::unprepared($this->normalizedCreateStatement($table, $statements[$table]));
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::TABLES) as $table) {
            Schema::dropIfExists($table);
        }
    }

    /**
     * @return array<string, string>
     */
    private function findCreateStatements(): array
    {
        $path = database_path('..\\nmwc\\nmwc_aswin_structure.sql');
        $found = [];

        foreach (SqlStatementFileReader::statements($path) as $statement) {
            foreach (self::TABLES as $table) {
                if (isset($found[$table])) {
                    continue;
                }

                if (preg_match('/^CREATE TABLE `'.preg_quote($table, '/').'`/i', $statement) === 1) {
                    $found[$table] = $statement;
                }
            }

            if (count($found) === count(self::TABLES)) {
                break;
            }
        }

        $missing = array_values(array_diff(self::TABLES, array_keys($found)));

        if ($missing !== []) {
            throw new \RuntimeException(
                'Unable to find CREATE TABLE statements for: '.implode(', ', $missing).' in '.$path
            );
        }

        return $found;
    }

    private function normalizedCreateStatement(string $table, string $statement): string
    {
        $prefix = $this->tablePrefix();

        $statement = preg_replace(
            '/^CREATE TABLE `'.preg_quote($table, '/').'`/i',
            "CREATE TABLE IF NOT EXISTS `{$prefix}{$table}`",
            $statement
        ) ?? $statement;

        if ($prefix !== '') {
            $relatedTables = array_unique(array_merge(self::TABLES, [
                'invoiceheader',
                'invoicedetail',
                'salesorderheader',
                'salesorderdetail',
                'startendday',
                'customermaster',
                'routemaster',
                'itemmaster',
                'promotioncontrol',
                'customeroperationscontrol',
                'cashcheckdetail',
                'customerinventorydetail',
                'posmaster',
                'salesman',
                'company',
            ]));

            foreach ($relatedTables as $relatedTable) {
                $statement = str_replace("`{$relatedTable}`", "`{$prefix}{$relatedTable}`", $statement);
            }
        }

        $statement = preg_replace(
            '/,\s*CONSTRAINT\s+`[^`]+`\s+FOREIGN KEY\s*\([^)]+\)\s+REFERENCES\s+`[^`]+`\s*\([^)]+\)\s+ON DELETE\s+\w+\s+ON UPDATE\s+\w+/i',
            '',
            $statement
        ) ?? $statement;

        return $statement;
    }

    private function tablePrefix(): string
    {
        return (string) config('database.connections.' . config('database.default') . '.prefix', '');
    }
};
