<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = $this->tableName('tbl_device');

        if (!Schema::hasTable('tbl_device')) {
            DB::unprepared("
                CREATE TABLE `{$table}` (
                    `primary_key` int NOT NULL AUTO_INCREMENT,
                    `company_id` int NOT NULL,
                    `device_id` varchar(255) NOT NULL,
                    `remarks` varchar(255) DEFAULT NULL,
                    PRIMARY KEY (`device_id`),
                    KEY `primary_key` (`primary_key`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        if (Schema::hasTable('devicemaster')) {
            $deviceMasterTable = $this->tableName('devicemaster');

            DB::statement("
                INSERT INTO `{$table}` (`company_id`, `device_id`, `remarks`)
                SELECT
                    COALESCE(NULLIF(`companyid`, 0), 1) AS `company_id`,
                    `deviceid` AS `device_id`,
                    `remarks`
                FROM `{$deviceMasterTable}`
                WHERE `deviceid` IS NOT NULL
                  AND `deviceid` <> ''
                  AND NOT EXISTS (
                      SELECT 1
                      FROM `{$table}` legacy
                      WHERE legacy.`device_id` = `{$deviceMasterTable}`.`deviceid`
                  )
            ");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_device');
    }

    private function tableName(string $table): string
    {
        $prefix = (string) config('database.connections.' . config('database.default') . '.prefix', '');

        return $prefix . $table;
    }
};
