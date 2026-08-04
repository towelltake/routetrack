<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('routemaster')) {
            Schema::table('routemaster', function (Blueprint $table) {
                if (! Schema::hasColumn('routemaster', 'autojp_enabled')) {
                    $table->unsignedTinyInteger('autojp_enabled')->default(0)->after('enablegps');
                }

                if (! Schema::hasColumn('routemaster', 'autojp_work_start_time')) {
                    $table->time('autojp_work_start_time')->nullable()->after('autojp_enabled');
                }

                if (! Schema::hasColumn('routemaster', 'autojp_work_end_time')) {
                    $table->time('autojp_work_end_time')->nullable()->after('autojp_work_start_time');
                }

                if (! Schema::hasColumn('routemaster', 'autojp_working_days')) {
                    $table->string('autojp_working_days', 32)->nullable()->after('autojp_work_end_time');
                }
            });
        }

        if (Schema::hasTable('customermaster')) {
            Schema::table('customermaster', function (Blueprint $table) {
                if (! Schema::hasColumn('customermaster', 'delivery_slot_from')) {
                    $table->time('delivery_slot_from')->nullable()->after('customerphone');
                }

                if (! Schema::hasColumn('customermaster', 'delivery_slot_to')) {
                    $table->time('delivery_slot_to')->nullable()->after('delivery_slot_from');
                }

                if (! Schema::hasColumn('customermaster', 'autojp_priority')) {
                    $table->integer('autojp_priority')->default(0)->after('delivery_slot_to');
                }

                if (! Schema::hasColumn('customermaster', 'allow_cross_route_jp')) {
                    $table->unsignedTinyInteger('allow_cross_route_jp')->default(0)->after('autojp_priority');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('customermaster')) {
            Schema::table('customermaster', function (Blueprint $table) {
                foreach (['allow_cross_route_jp', 'autojp_priority', 'delivery_slot_to', 'delivery_slot_from'] as $column) {
                    if (Schema::hasColumn('customermaster', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('routemaster')) {
            Schema::table('routemaster', function (Blueprint $table) {
                foreach (['autojp_working_days', 'autojp_work_end_time', 'autojp_work_start_time', 'autojp_enabled'] as $column) {
                    if (Schema::hasColumn('routemaster', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
