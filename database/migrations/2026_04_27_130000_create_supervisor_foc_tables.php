<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('supervisor_foc')) {
            Schema::create('supervisor_foc', function (Blueprint $table) {
                $table->bigIncrements('contractid');
                $table->unsignedBigInteger('supervisorcode');
                $table->date('creationdate')->nullable();
                $table->text('remarks')->nullable();
                $table->date('startdate')->nullable();
                $table->date('enddate')->nullable();
                $table->tinyInteger('active')->default(1);
                $table->unsignedBigInteger('depotcode')->nullable();
            });
        }

        if (!Schema::hasTable('supervisor_foc_detail')) {
            Schema::create('supervisor_foc_detail', function (Blueprint $table) {
                $table->unsignedBigInteger('contractid');
                $table->unsignedBigInteger('supervisorcode');
                $table->unsignedBigInteger('itemcode');
                $table->bigInteger('freequantity')->default(0);
                $table->string('remarks', 200)->nullable();
                $table->date('editdate')->nullable();
                $table->primary(['contractid', 'itemcode']);
            });
        }

        if (!Schema::hasTable('supervisor_foc_balance')) {
            Schema::create('supervisor_foc_balance', function (Blueprint $table) {
                $table->unsignedBigInteger('contractid');
                $table->unsignedBigInteger('supervisorcode');
                $table->unsignedBigInteger('itemcode');
                $table->bigInteger('originalqty')->default(0);
                $table->bigInteger('balanceqty')->default(0);
                $table->date('startdate')->nullable();
                $table->primary(['contractid', 'itemcode']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('supervisor_foc_balance');
        Schema::dropIfExists('supervisor_foc_detail');
        Schema::dropIfExists('supervisor_foc');
    }
};
