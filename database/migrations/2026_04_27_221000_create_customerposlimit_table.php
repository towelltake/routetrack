<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('customerposlimit')) {
            return;
        }

        Schema::create('customerposlimit', function (Blueprint $table) {
            $table->bigIncrements('primary_key');
            $table->unsignedBigInteger('customercode')->index();
            $table->integer('poslimit')->nullable();
            $table->integer('posbalance')->nullable();
            $table->string('created', 30)->nullable();
            $table->dateTime('cdat')->nullable();
            $table->string('modified', 30)->nullable();
            $table->dateTime('mdat')->nullable();

            $table->foreign('customercode', 'fk_customerposlimit_customermaster')
                ->references('customercode')
                ->on('customermaster')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('customerposlimit')) {
            return;
        }

        Schema::table('customerposlimit', function (Blueprint $table) {
            $table->dropForeign('fk_customerposlimit_customermaster');
        });

        Schema::dropIfExists('customerposlimit');
    }
};
