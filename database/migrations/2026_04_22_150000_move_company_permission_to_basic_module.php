<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('moduledetail')) {
            DB::table('moduledetail')
                ->where('formname', 'Company')
                ->update(['moduleid' => 2]);
        }

        if (Schema::hasTable('userdetail')) {
            DB::table('userdetail')
                ->where('formname', 'Company')
                ->update(['moduleid' => 2]);
        }

        if (Schema::hasTable('usertypedetail')) {
            DB::table('usertypedetail')
                ->where('formname', 'Company')
                ->update(['moduleid' => 2]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('moduledetail')) {
            DB::table('moduledetail')
                ->where('formname', 'Company')
                ->update(['moduleid' => 3]);
        }

        if (Schema::hasTable('userdetail')) {
            DB::table('userdetail')
                ->where('formname', 'Company')
                ->update(['moduleid' => 3]);
        }

        if (Schema::hasTable('usertypedetail')) {
            DB::table('usertypedetail')
                ->where('formname', 'Company')
                ->update(['moduleid' => 3]);
        }
    }
};
