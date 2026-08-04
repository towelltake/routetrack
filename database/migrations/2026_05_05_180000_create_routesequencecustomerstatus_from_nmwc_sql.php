<?php

use App\Support\SqlStatementFileReader;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'routesequencecustomerstatus';

    public function up(): void
    {
        if (Schema::hasTable(self::TABLE)) {
            return;
        }

        DB::unprepared($this->normalizedCreateStatement());
    }

    public function down(): void
    {
        Schema::dropIfExists(self::TABLE);
    }

    private function normalizedCreateStatement(): string
    {
        $statement = $this->findCreateStatement();
        $prefix = $this->tablePrefix();

        $statement = preg_replace(
            '/^CREATE TABLE `'.preg_quote(self::TABLE, '/').'`/i',
            "CREATE TABLE IF NOT EXISTS `{$prefix}".self::TABLE.'`',
            $statement
        ) ?? $statement;

        if ($prefix !== '') {
            foreach (['routesequencecustomerstatus', 'customermaster', 'startendday'] as $table) {
                $statement = str_replace("`{$table}`", "`{$prefix}{$table}`", $statement);
            }
        }

        return $statement;
    }

    private function findCreateStatement(): string
    {
        $path = database_path('..\\nmwc\\nmwc_aswin_structure.sql');

        foreach (SqlStatementFileReader::statements($path) as $statement) {
            if (preg_match('/^CREATE TABLE `'.preg_quote(self::TABLE, '/').'`/i', $statement) === 1) {
                return $statement;
            }
        }

        throw new \RuntimeException('Unable to find CREATE TABLE statement for '.self::TABLE.' in '.$path);
    }

    private function tablePrefix(): string
    {
        return (string) config('database.connections.' . config('database.default') . '.prefix', '');
    }
};
