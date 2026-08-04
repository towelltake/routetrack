<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('userdetail') && !Schema::hasColumn('userdetail', 'viewdata')) {
            Schema::table('userdetail', function (Blueprint $table) {
                $table->integer('viewdata')->default(0)->nullable()->after('formdescription');
            });
        }

        if (Schema::hasTable('usertypedetail') && !Schema::hasColumn('usertypedetail', 'viewdata')) {
            Schema::table('usertypedetail', function (Blueprint $table) {
                $table->integer('viewdata')->default(0)->nullable()->after('formdescription');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('userdetail') && Schema::hasColumn('userdetail', 'viewdata')) {
            Schema::table('userdetail', function (Blueprint $table) {
                $table->dropColumn('viewdata');
            });
        }

        if (Schema::hasTable('usertypedetail') && Schema::hasColumn('usertypedetail', 'viewdata')) {
            Schema::table('usertypedetail', function (Blueprint $table) {
                $table->dropColumn('viewdata');
            });
        }
    }
};
