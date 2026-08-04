<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usertypedetail', function (Blueprint $table) {
            $table->bigIncrements('primary_key');
            $table->unsignedBigInteger('usertypeid')->nullable();
            $table->string('formname', 50)->nullable();
            $table->string('formdescription', 50)->nullable();
            $table->integer('viewdata')->default(0)->nullable();
            $table->integer('readdata')->default(0)->nullable();
            $table->integer('updatedata')->default(0)->nullable();
            $table->integer('insertdata')->default(0)->nullable();
            $table->integer('deletedata')->default(0)->nullable();
            $table->integer('allpermissions')->default(0)->nullable();
            $table->decimal('moduleid', 18, 0)->nullable();
            $table->decimal('formid', 18, 0)->nullable();

            $table->index('usertypeid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usertypedetail');
    }
};
