<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('autojp_plan_headers')) {
            Schema::create('autojp_plan_headers', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('routecode');
                $table->unsignedInteger('week_number');
                $table->unsignedInteger('route_type')->nullable();
                $table->time('work_start_time')->nullable();
                $table->time('work_end_time')->nullable();
                $table->string('working_days', 32)->nullable();
                $table->unsignedInteger('lookback_weeks')->default(8);
                $table->string('status', 20)->default('draft');
                $table->unsignedInteger('customer_count')->default(0);
                $table->unsignedInteger('external_customer_count')->default(0);
                $table->string('generated_by', 100)->nullable();
                $table->timestamp('generated_at')->nullable();
                $table->string('published_by', 100)->nullable();
                $table->timestamp('published_at')->nullable();
                $table->timestamps();

                $table->index(['routecode', 'week_number']);
            });
        }

        if (! Schema::hasTable('autojp_plan_items')) {
            Schema::create('autojp_plan_items', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('plan_id');
                $table->unsignedBigInteger('customercode');
                $table->unsignedBigInteger('home_routecode')->nullable();
                $table->unsignedBigInteger('assigned_routecode');
                $table->unsignedTinyInteger('assigned_weekday')->nullable();
                $table->unsignedInteger('assigned_sequence')->default(0);
                $table->time('delivery_slot_from')->nullable();
                $table->time('delivery_slot_to')->nullable();
                $table->time('planned_start_time')->nullable();
                $table->time('planned_end_time')->nullable();
                $table->date('last_invoice_date')->nullable();
                $table->date('last_order_date')->nullable();
                $table->unsignedInteger('serviced_visits')->default(0);
                $table->unsignedInteger('scheduled_visits')->default(0);
                $table->time('avg_visit_start_time')->nullable();
                $table->unsignedInteger('avg_visit_duration_minutes')->default(20);
                $table->unsignedTinyInteger('preferred_weekday')->nullable();
                $table->decimal('score', 10, 2)->default(0);
                $table->string('source', 20)->default('home');
                $table->text('generation_notes')->nullable();
                $table->timestamps();

                $table->unique(['plan_id', 'customercode']);
                $table->index(['assigned_weekday', 'assigned_sequence']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('autojp_plan_items');
        Schema::dropIfExists('autojp_plan_headers');
    }
};
