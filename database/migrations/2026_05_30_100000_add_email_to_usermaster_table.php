<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('usermaster') || Schema::hasColumn('usermaster', 'email')) {
            return;
        }

        Schema::table('usermaster', function (Blueprint $table) {
            $table->string('email', 30)->nullable()->after('username');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('usermaster') || !Schema::hasColumn('usermaster', 'email')) {
            return;
        }

        Schema::table('usermaster', function (Blueprint $table) {
            $table->dropColumn('email');
        });
    }
};
