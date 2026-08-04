<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('moduledetail')) {
            return;
        }

        $loyaltyKeyOrder = DB::table('moduledetail')
            ->whereRaw('LOWER(TRIM(formname)) = ?', ['loyalty key'])
            ->value('order');

        if ($loyaltyKeyOrder === null) {
            return;
        }

        DB::table('moduledetail')
            ->whereRaw('LOWER(TRIM(formname)) = ?', ['supervisor free contract'])
            ->update(['order' => $loyaltyKeyOrder + 1]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('moduledetail')) {
            return;
        }

        $qualGroupOrder = DB::table('moduledetail')
            ->whereRaw('LOWER(TRIM(formname)) = ?', ['qualification group'])
            ->value('order');

        if ($qualGroupOrder === null) {
            return;
        }

        DB::table('moduledetail')
            ->whereRaw('LOWER(TRIM(formname)) = ?', ['supervisor free contract'])
            ->update(['order' => $qualGroupOrder - 1]);
    }
};
