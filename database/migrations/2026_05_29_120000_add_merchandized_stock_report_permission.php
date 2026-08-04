<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('moduleheader') || !Schema::hasTable('moduledetail')) {
            return;
        }

        $moduleId = (int) DB::table('moduleheader')->whereRaw('LOWER(TRIM(modulename)) = ?', ['reports'])->value('moduleid');
        if ($moduleId <= 0) {
            $moduleId = 10;
        }

        $existing = DB::table('moduledetail')
            ->whereRaw('LOWER(TRIM(formname)) = ?', ['merchandized stock'])
            ->first();

        if ($existing) {
            DB::table('moduledetail')
                ->where('formid', $existing->formid)
                ->update([
                    'moduleid' => $moduleId,
                    'formname' => 'Merchandized Stock',
                    'formdescription' => 'Merchandized Stock',
                ]);

            return;
        }

        $nextFormId = (int) DB::table('moduledetail')->max('formid') + 1;
        $nextOrder = ((int) DB::table('moduledetail')->where('moduleid', $moduleId)->max('order')) + 1;
        if ($nextOrder <= 0) {
            $nextOrder = 1;
        }

        DB::table('moduledetail')->insert([
            'formid' => $nextFormId,
            'moduleid' => $moduleId,
            'formname' => 'Merchandized Stock',
            'formdescription' => 'Merchandized Stock',
            'order' => $nextOrder,
        ]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('moduledetail')) {
            return;
        }

        DB::table('moduledetail')
            ->whereRaw('LOWER(TRIM(formname)) = ?', ['merchandized stock'])
            ->delete();
    }
};
