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

        $organisationModuleId = DB::table('moduleheader')
            ->whereRaw('LOWER(TRIM(modulename)) = ?', ['organisation'])
            ->value('moduleid');

        if (!$organisationModuleId) {
            return;
        }

        $order = (int) DB::table('moduledetail')
            ->where('moduleid', $organisationModuleId)
            ->max('order');

        $existingId = DB::table('moduledetail')
            ->whereRaw('LOWER(TRIM(formname)) = ?', ['route category'])
            ->value('formid');

        $payload = [
            'formname' => 'Route Category',
            'formdescription' => 'Route Category',
            'moduleid' => $organisationModuleId,
            'order' => $order + 1,
        ];

        if ($existingId) {
            DB::table('moduledetail')
                ->where('formid', $existingId)
                ->update($payload);
        } else {
            DB::table('moduledetail')->insert($payload);
            $existingId = DB::table('moduledetail')
                ->whereRaw('LOWER(TRIM(formname)) = ?', ['route category'])
                ->value('formid');
        }

        if (!$existingId) {
            return;
        }

        if (Schema::hasTable('userdetail')) {
            DB::table('userdetail')
                ->whereRaw('LOWER(TRIM(formname)) = ?', ['route category'])
                ->update([
                    'moduleid' => $organisationModuleId,
                    'formid' => $existingId,
                    'formdescription' => 'Route Category',
                ]);
        }

        if (Schema::hasTable('usertypedetail')) {
            DB::table('usertypedetail')
                ->whereRaw('LOWER(TRIM(formname)) = ?', ['route category'])
                ->update([
                    'moduleid' => $organisationModuleId,
                    'formid' => $existingId,
                    'formdescription' => 'Route Category',
                ]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('moduleheader') || !Schema::hasTable('moduledetail')) {
            return;
        }

        DB::table('moduledetail')
            ->whereRaw('LOWER(TRIM(formname)) = ?', ['route category'])
            ->delete();

        if (Schema::hasTable('userdetail')) {
            DB::table('userdetail')
                ->whereRaw('LOWER(TRIM(formname)) = ?', ['route category'])
                ->delete();
        }

        if (Schema::hasTable('usertypedetail')) {
            DB::table('usertypedetail')
                ->whereRaw('LOWER(TRIM(formname)) = ?', ['route category'])
                ->delete();
        }
    }
};
