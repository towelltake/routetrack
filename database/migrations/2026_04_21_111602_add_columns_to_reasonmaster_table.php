<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('reasonmaster', function (Blueprint $table) {
            $table->string('code', 20)->nullable()->after('id');
            $table->string('description', 100)->nullable()->after('code');
            $table->string('arbdescription', 100)->nullable()->after('description');
            $table->string('alternatecode', 20)->nullable()->after('arbdescription');
            $table->string('type', 20)->nullable()->after('alternatecode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reasonmaster', function (Blueprint $table) {
            $table->dropColumn(['code', 'description', 'arbdescription', 'alternatecode', 'type']);
        });
    }
};
