<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('company')) {
            return;
        }

        Schema::table('company', function (Blueprint $table) {
            $table->string('address', 255)->nullable()->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('company')) {
            return;
        }

        Schema::table('company', function (Blueprint $table) {
            $table->string('address', 50)->nullable()->change();
        });
    }
};
