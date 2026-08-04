<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('moduledetail')) {
            return;
        }

        $customerFormId = DB::table('moduledetail')
            ->whereRaw('LOWER(TRIM(formname)) = ?', ['account customer'])
            ->value('formid');

        $categoryOrder = DB::table('moduledetail')
            ->whereRaw('LOWER(TRIM(formname)) = ?', ['account customer category'])
            ->value('order');

        if (!$customerFormId || $categoryOrder === null) {
            return;
        }

        DB::table('moduledetail')
            ->where('formid', $customerFormId)
            ->update([
                'order' => ((int) $categoryOrder) + 1,
            ]);
    }

    public function down(): void
    {
    }
};
