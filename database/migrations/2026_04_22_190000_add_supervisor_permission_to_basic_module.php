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

        $basicModuleId = DB::table('moduleheader')
            ->whereRaw('LOWER(TRIM(modulename)) = ?', ['basic'])
            ->value('moduleid');

        if (!$basicModuleId) {
            $basicModuleId = 2;
        }

        $order = (int) DB::table('moduledetail')
            ->where('moduleid', $basicModuleId)
            ->max('order');

        $existingId = DB::table('moduledetail')
            ->whereRaw('LOWER(TRIM(formname)) = ?', ['supervisor'])
            ->value('formid');

        $payload = [
            'formname' => 'Supervisor',
            'formdescription' => 'Manage Supervisor',
            'moduleid' => $basicModuleId,
            'order' => $order + 1,
        ];

        if ($existingId) {
            DB::table('moduledetail')
                ->where('formid', $existingId)
                ->update($payload);

            return;
        }

        DB::table('moduledetail')->insert($payload);
    }

    public function down(): void
    {
        if (!Schema::hasTable('moduledetail')) {
            return;
        }

        DB::table('moduledetail')
            ->whereRaw('LOWER(TRIM(formname)) = ?', ['supervisor'])
            ->delete();
    }
};
