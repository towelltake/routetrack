<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $versionTable = $this->tableName('tbl_version');
        $syncServiceTable = $this->tableName('tbl_syncservice');

        if (!Schema::hasTable('tbl_version')) {
            DB::unprepared("
                CREATE TABLE `{$versionTable}` (
                    `url` varchar(255) DEFAULT NULL,
                    `verno` varchar(255) DEFAULT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        if (!Schema::hasTable('tbl_syncservice')) {
            DB::unprepared("
                CREATE TABLE `{$syncServiceTable}` (
                    `userid` int NOT NULL,
                    `deviceid` varchar(255) NOT NULL,
                    `syncdate` datetime NOT NULL,
                    `routecode` int NOT NULL,
                    `synctime` time NOT NULL,
                    `synctype` enum('1','2') NOT NULL COMMENT '1 => upload, 2=> download',
                    `routeclosed` enum('0','1') NOT NULL COMMENT '0 => start, 1=> close',
                    `routekey` int DEFAULT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_syncservice');
        Schema::dropIfExists('tbl_version');
    }

    private function tableName(string $table): string
    {
        $prefix = (string) config('database.connections.' . config('database.default') . '.prefix', '');

        return $prefix . $table;
    }
};
