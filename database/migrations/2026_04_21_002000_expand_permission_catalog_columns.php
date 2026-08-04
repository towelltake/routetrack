<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('moduledetail')) {
            Schema::table('moduledetail', function (Blueprint $table) {
                $table->string('formname', 100)->nullable()->change();
                $table->string('formdescription', 255)->nullable()->change();
            });
        }

        if (Schema::hasTable('userdetail')) {
            Schema::table('userdetail', function (Blueprint $table) {
                $table->string('formname', 100)->nullable()->change();
                $table->string('formdescription', 255)->nullable()->change();
            });
        }

        if (Schema::hasTable('usertypedetail')) {
            Schema::table('usertypedetail', function (Blueprint $table) {
                $table->string('formname', 100)->nullable()->change();
                $table->string('formdescription', 255)->nullable()->change();
            });
        }

        if (Schema::hasTable('usermaster')) {
            Schema::table('usermaster', function (Blueprint $table) {
                $table->string('password', 255)->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('usertypedetail')) {
            Schema::table('usertypedetail', function (Blueprint $table) {
                $table->string('formname', 50)->nullable()->change();
                $table->string('formdescription', 50)->nullable()->change();
            });
        }

        if (Schema::hasTable('userdetail')) {
            Schema::table('userdetail', function (Blueprint $table) {
                $table->string('formname', 50)->nullable()->change();
                $table->string('formdescription', 50)->nullable()->change();
            });
        }

        if (Schema::hasTable('moduledetail')) {
            Schema::table('moduledetail', function (Blueprint $table) {
                $table->string('formname', 50)->nullable()->change();
                $table->string('formdescription', 50)->nullable()->change();
            });
        }

        if (Schema::hasTable('usermaster')) {
            Schema::table('usermaster', function (Blueprint $table) {
                $table->string('password', 50)->nullable()->change();
            });
        }
    }
};
