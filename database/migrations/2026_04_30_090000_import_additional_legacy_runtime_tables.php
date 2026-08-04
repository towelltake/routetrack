<?php

use App\Support\LegacySchemaBootstrap;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = [
        'ardetail',
        'arheader',
        'beardetail',
        'bearheader',
        'cashcheckdetail',
        'cashierreceiptdetails',
        'cashierreceiptheader',
        'companygroup',
        'controlpanel',
        'customerinventorydetail',
        'customerinvoice',
        'customeroperationscontrol',
        'customerpricing',
        'customersurveycontrol',
        'customersurveycontrolheader',
        'customersurveydefassign',
        'customersurveykeyplan',
        'dcardetail',
        'dcarheader',
        'deliverydetail',
        'deliveryheader',
        'groupmaster',
        'inventorysummarydetail',
        'inventorytransactiondetail',
        'inventorytransactionheader',
        'invoicedetail',
        'invoiceheader',
        'itemmustheader',
        'itempackagemaster',
        'majorcategory',
        'monthlysummarizationperroute',
        'pdcclearenceheader',
        'promotiondetail_temp',
        'routegoal',
        'routeitemgrp',
        'routeitemmapping',
        'salescalender',
        'salesorderdetail',
        'salesorderheader',
        'setup',
        'startendday',
        'startingloaddetail',
        'submajorcategory',
        'suggestedsalesinvoice',
    ];

    public function up(): void
    {
        $statements = LegacySchemaBootstrap::loadStatements(self::TABLES);

        Schema::disableForeignKeyConstraints();

        try {
            foreach ($statements as $table => $statement) {
                if (Schema::hasTable($table)) {
                    continue;
                }

                DB::unprepared($this->normalizeCreateStatement($table, $statement));
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

    private function normalizeCreateStatement(string $table, string $statement): string
    {
        $prefix = $this->tablePrefix();

        $statement = preg_replace(
            '/^CREATE TABLE `'.preg_quote($table, '/').'`/i',
            "CREATE TABLE IF NOT EXISTS `{$prefix}{$table}`",
            $statement
        ) ?? $statement;

        if ($prefix !== '') {
            foreach (self::TABLES as $legacyTable) {
                $statement = str_replace(
                    "`{$legacyTable}`",
                    "`{$prefix}{$legacyTable}`",
                    $statement
                );
            }
        }

        $statement = preg_replace('/,\s*CONSTRAINT\s+`[^`]+`\s+FOREIGN KEY\s*\([^)]+\)\s+REFERENCES\s+`[^`]+`\s*\([^)]+\)\s+ON DELETE\s+\w+\s+ON UPDATE\s+\w+/i', '', $statement) ?? $statement;
        $statement = preg_replace('/\s+AUTO_INCREMENT=\d+/i', '', $statement) ?? $statement;
        $statement = preg_replace('/\s+CHECKSUM=\d+/i', '', $statement) ?? $statement;
        $statement = preg_replace('/\s+DELAY_KEY_WRITE=\d+/i', '', $statement) ?? $statement;
        $statement = preg_replace('/\s+ROW_FORMAT=\w+/i', '', $statement) ?? $statement;

        return $statement;
    }

    private function tablePrefix(): string
    {
        return (string) config('database.connections.' . config('database.default') . '.prefix', '');
    }

};
