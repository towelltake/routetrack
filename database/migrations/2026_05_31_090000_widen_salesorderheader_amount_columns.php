<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sales order header totals were imported from the legacy schema as
     * double(8,4), which overflows above 9999.9999. Widen the monetary
     * columns to match the decimal precision already used by invoice tables.
     */
    public function up(): void
    {
        if (!Schema::hasTable('salesorderheader')) {
            return;
        }

        $table = $this->qualifiedTable('salesorderheader');

        DB::statement("
            ALTER TABLE {$table}
            MODIFY `totalinvoiceamount` DECIMAL(19,4) NULL DEFAULT '0.0000',
            MODIFY `totalsalesamount` DECIMAL(19,4) NULL DEFAULT '0.0000',
            MODIFY `totalreturnamount` DECIMAL(19,4) NULL DEFAULT '0.0000',
            MODIFY `totaldamagedamount` DECIMAL(19,4) NULL DEFAULT '0.0000',
            MODIFY `totalfreesampleamount` DECIMAL(19,4) NULL DEFAULT '0.0000'
        ");
    }

    public function down(): void
    {
        if (!Schema::hasTable('salesorderheader')) {
            return;
        }

        $table = $this->qualifiedTable('salesorderheader');

        DB::statement("
            ALTER TABLE {$table}
            MODIFY `totalinvoiceamount` DOUBLE(8,4) NULL DEFAULT '0.0000',
            MODIFY `totalsalesamount` DOUBLE(8,4) NULL DEFAULT '0.0000',
            MODIFY `totalreturnamount` DOUBLE(8,4) NULL DEFAULT '0.0000',
            MODIFY `totaldamagedamount` DOUBLE(8,4) NULL DEFAULT '0.0000',
            MODIFY `totalfreesampleamount` DOUBLE(8,4) NULL DEFAULT '0.0000'
        ");
    }

    private function qualifiedTable(string $table): string
    {
        $prefix = (string) config('database.connections.' . config('database.default') . '.prefix', '');

        return '`' . $prefix . $table . '`';
    }
};
