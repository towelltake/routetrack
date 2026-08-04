<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('otplogdetail')) {
            return;
        }

        Schema::create('otplogdetail', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('routecode')->nullable();
            $table->unsignedBigInteger('customercode')->nullable();
            $table->string('username', 100);
            $table->string('otptype', 100);
            $table->date('otpdate')->nullable();
            $table->time('otptime')->nullable();
            $table->dateTime('cdate')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otplogdetail');
    }
};
