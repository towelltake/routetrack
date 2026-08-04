<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('expiryreturnreasons')) {
            Schema::create('expiryreturnreasons', function (Blueprint $table) {
                $table->bigIncrements('code');
                $table->string('alternatecode', 50)->nullable();
                $table->string('description', 50)->nullable();
                $table->string('arbdescription', 100)->nullable();
                $table->string('hhcdescription', 100)->nullable();
                $table->string('created', 25)->nullable();
                $table->dateTime('cdat')->nullable();
                $table->string('modified', 25)->nullable();
                $table->dateTime('mdat')->nullable();
            });
        }

        if (!Schema::hasTable('expreasons')) {
            Schema::create('expreasons', function (Blueprint $table) {
                $table->bigIncrements('code');
                $table->string('alternatecode', 50)->nullable();
                $table->string('description', 50)->nullable();
                $table->string('arbdescription', 100)->nullable();
                $table->string('hhcdescription', 100)->nullable();
                $table->integer('allowliterentry')->nullable();
                $table->string('created', 25)->nullable();
                $table->dateTime('cdat')->nullable();
                $table->string('modified', 25)->nullable();
                $table->dateTime('mdat')->nullable();
            });
        }

        if (!Schema::hasTable('freegoodreasons')) {
            Schema::create('freegoodreasons', function (Blueprint $table) {
                $table->bigIncrements('reason_code');
                $table->string('alternatereasoncode', 50)->nullable();
                $table->string('reason_desc', 50)->nullable();
                $table->string('reason_arb_desc', 100)->nullable();
                $table->string('created', 25)->nullable();
                $table->dateTime('cdat')->nullable();
                $table->string('modified', 25)->nullable();
                $table->dateTime('mdat')->nullable();
            });
        }

        if (!Schema::hasTable('retitmreasons')) {
            Schema::create('retitmreasons', function (Blueprint $table) {
                $table->bigIncrements('code');
                $table->string('alternatecode', 50)->nullable();
                $table->string('description', 50)->nullable();
                $table->string('arbdescription', 100)->nullable();
                $table->string('hhcdescription', 100)->nullable();
                $table->string('created', 25)->nullable();
                $table->dateTime('cdat')->nullable();
                $table->string('modified', 25)->nullable();
                $table->dateTime('mdat')->nullable();
                $table->integer('activestatus')->default(1)->nullable();
            });
        }

        if (!Schema::hasTable('nonservreasons')) {
            Schema::create('nonservreasons', function (Blueprint $table) {
                $table->bigIncrements('code');
                $table->string('alternatecode', 50)->nullable();
                $table->string('description', 50)->nullable();
                $table->string('arbdescription', 100)->nullable();
                $table->string('hhcdescription', 100)->nullable();
                $table->string('created', 25)->nullable();
                $table->dateTime('cdat')->nullable();
                $table->string('modified', 25)->nullable();
                $table->dateTime('mdat')->nullable();
            });
        }

        if (!Schema::hasTable('voidreasons')) {
            Schema::create('voidreasons', function (Blueprint $table) {
                $table->bigIncrements('code');
                $table->string('alternatecode', 50)->nullable();
                $table->string('description', 50)->nullable();
                $table->string('arbdescription', 100)->nullable();
                $table->string('hhcdescription', 100)->nullable();
                $table->string('created', 25)->nullable();
                $table->dateTime('cdat')->nullable();
                $table->string('modified', 25)->nullable();
                $table->dateTime('mdat')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('voidreasons');
        Schema::dropIfExists('nonservreasons');
        Schema::dropIfExists('retitmreasons');
        Schema::dropIfExists('freegoodreasons');
        Schema::dropIfExists('expreasons');
        Schema::dropIfExists('expiryreturnreasons');
    }
};
