<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('warehousestock')) {
            return;
        }

        DB::unprepared($this->statement());
    }

    public function down(): void
    {
        Schema::dropIfExists('warehousestock');
    }

    private function statement(): string
    {
        $table = $this->tableName('warehousestock');

        return <<<SQL
CREATE TABLE `{$table}` (
  `warehousecode` varchar(20) NOT NULL,
  `trandate` date NOT NULL,
  `itemcode` decimal(10,0) NOT NULL,
  `cases` int DEFAULT NULL,
  `units` int DEFAULT NULL,
  `totunits` int DEFAULT NULL,
  `upc` int NOT NULL,
  `caseprice` decimal(18,4) DEFAULT NULL,
  `eachprice` decimal(18,4) DEFAULT NULL,
  `balanceqty` decimal(10,0) DEFAULT NULL,
  PRIMARY KEY (`warehousecode`,`itemcode`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1
SQL;
    }

    private function tableName(string $table): string
    {
        $prefix = (string) config('database.connections.' . config('database.default') . '.prefix', '');

        return $prefix . $table;
    }
};
