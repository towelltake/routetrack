<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('devicemaster')) {
            Schema::create('devicemaster', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('deviceid', 191)->unique();
                $table->string('remarks', 255)->nullable();
                $table->unsignedBigInteger('companyid')->nullable();
                $table->integer('statusflag')->default(1);

                $table->foreign('companyid', 'fk_devicemaster_company')
                    ->references('cmpycode')
                    ->on('company')
                    ->nullOnDelete()
                    ->cascadeOnUpdate();
            });
        }

        if (!Schema::hasTable('salesmanmaster')) {
            Schema::create('salesmanmaster', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('code', 50)->nullable();
                $table->string('salesmanname', 50);
                $table->string('arbsalesmanname', 50)->nullable();
                $table->string('contactnumber', 50)->nullable();
                $table->unsignedBigInteger('companyid')->nullable();
                $table->string('username', 255)->nullable();
                $table->string('userpassword', 255)->nullable();
                $table->integer('statusflag')->default(1);

                $table->foreign('companyid', 'fk_salesmanmaster_company')
                    ->references('cmpycode')
                    ->on('company')
                    ->nullOnDelete()
                    ->cascadeOnUpdate();
            });
        }

        if (!Schema::hasTable('vehiclemaster')) {
            Schema::create('vehiclemaster', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('code', 50)->nullable();
                $table->string('vandescription', 50);
                $table->string('arbvandescription', 50)->nullable();
                $table->string('vehicleregistration', 50)->nullable();
                $table->string('vanmodel', 50)->nullable();
                $table->string('vantype', 50)->nullable();
                $table->unsignedBigInteger('companyid')->nullable();
                $table->integer('statusflag')->default(1);

                $table->foreign('companyid', 'fk_vehiclemaster_company')
                    ->references('cmpycode')
                    ->on('company')
                    ->nullOnDelete()
                    ->cascadeOnUpdate();
            });
        }

        if (!Schema::hasTable('supervisorfreegoods')) {
            Schema::create('supervisorfreegoods', function (Blueprint $table) {
                $table->bigIncrements('contractid');
                $table->unsignedBigInteger('supervisorcode');
                $table->date('startdate')->nullable();
                $table->date('enddate')->nullable();
                $table->tinyInteger('active')->default(1);
                $table->unsignedBigInteger('depotcode')->nullable();
                $table->text('remarks')->nullable();
                $table->string('created', 30)->nullable();
                $table->dateTime('cdat')->nullable();
                $table->string('modified', 30)->nullable();
                $table->dateTime('mdat')->nullable();

                $table->foreign('supervisorcode', 'fk_supervisorfreegoods_supervisor')
                    ->references('supervisorcode')
                    ->on('supervisor')
                    ->cascadeOnDelete()
                    ->cascadeOnUpdate();

                $table->foreign('depotcode', 'fk_supervisorfreegoods_depot')
                    ->references('depotcode')
                    ->on('depotmaster')
                    ->nullOnDelete()
                    ->cascadeOnUpdate();
            });
        }

        if (!Schema::hasTable('supervisorfreegoodsdetail')) {
            Schema::create('supervisorfreegoodsdetail', function (Blueprint $table) {
                $table->unsignedBigInteger('contractid');
                $table->unsignedBigInteger('itemcode');
                $table->bigInteger('freequantity')->default(0);
                $table->bigInteger('balanceqty')->default(0);
                $table->bigInteger('originalqty')->default(0);
                $table->string('created', 30)->nullable();
                $table->dateTime('cdat')->nullable();
                $table->string('modified', 30)->nullable();
                $table->dateTime('mdat')->nullable();

                $table->primary(['contractid', 'itemcode'], 'pk_supervisorfreegoodsdetail');

                $table->foreign('contractid', 'fk_supervisorfreegoodsdetail_header')
                    ->references('contractid')
                    ->on('supervisorfreegoods')
                    ->cascadeOnDelete()
                    ->cascadeOnUpdate();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('supervisorfreegoodsdetail')) {
            Schema::table('supervisorfreegoodsdetail', function (Blueprint $table) {
                $table->dropForeign('fk_supervisorfreegoodsdetail_header');
            });
        }

        Schema::dropIfExists('supervisorfreegoodsdetail');

        if (Schema::hasTable('supervisorfreegoods')) {
            Schema::table('supervisorfreegoods', function (Blueprint $table) {
                $table->dropForeign('fk_supervisorfreegoods_supervisor');
                $table->dropForeign('fk_supervisorfreegoods_depot');
            });
        }

        Schema::dropIfExists('supervisorfreegoods');

        if (Schema::hasTable('vehiclemaster')) {
            Schema::table('vehiclemaster', function (Blueprint $table) {
                $table->dropForeign('fk_vehiclemaster_company');
            });
        }

        Schema::dropIfExists('vehiclemaster');

        if (Schema::hasTable('salesmanmaster')) {
            Schema::table('salesmanmaster', function (Blueprint $table) {
                $table->dropForeign('fk_salesmanmaster_company');
            });
        }

        Schema::dropIfExists('salesmanmaster');

        if (Schema::hasTable('devicemaster')) {
            Schema::table('devicemaster', function (Blueprint $table) {
                $table->dropForeign('fk_devicemaster_company');
            });
        }

        Schema::dropIfExists('devicemaster');
    }
};
