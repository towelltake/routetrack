<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('useraccesscodes')) {
            return;
        }

        Schema::create('useraccesscodes', function (Blueprint $table) {
            $table->string('username', 10)->nullable();
            $table->string('cmpycode', 200)->nullable();
            $table->string('depotcode', 200)->nullable();
            $table->string('areacode', 200)->nullable();
            $table->string('subareacode', 200)->nullable();
            $table->decimal('regionmstcode', 18, 0)->nullable();
            $table->decimal('countrycode', 18, 0)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('useraccesscodes');
    }
};
