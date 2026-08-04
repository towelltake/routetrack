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

        $accountModuleId = DB::table('moduleheader')
            ->whereRaw('LOWER(TRIM(modulename)) = ?', ['account'])
            ->value('moduleid');

        if (!$accountModuleId) {
            $accountModuleId = ((int) DB::table('moduleheader')->max('moduleid')) + 1;

            DB::table('moduleheader')->insert([
                'moduleid' => $accountModuleId,
                'modulename' => 'Account',
            ]);
        }

        $order = (int) DB::table('moduledetail')
            ->where('moduleid', $accountModuleId)
            ->max('order');

        $existingId = DB::table('moduledetail')
            ->whereRaw('LOWER(TRIM(formname)) = ?', ['message'])
            ->value('formid');

        $payload = [
            'formname' => 'Message',
            'formdescription' => 'Manage customer messages and note templates',
            'moduleid' => $accountModuleId,
            'order' => $order + 1,
        ];

        if ($existingId) {
            DB::table('moduledetail')
                ->where('formid', $existingId)
                ->update($payload);
        } else {
            DB::table('moduledetail')->insert($payload);
            $existingId = DB::table('moduledetail')
                ->whereRaw('LOWER(TRIM(formname)) = ?', ['message'])
                ->value('formid');
        }

        if (!$existingId) {
            return;
        }

        if (Schema::hasTable('userdetail')) {
            DB::table('userdetail')
                ->whereRaw('LOWER(TRIM(formname)) IN (?, ?)', ['message', 'customer message'])
                ->update([
                    'moduleid' => $accountModuleId,
                    'formid' => $existingId,
                    'formname' => 'Message',
                    'formdescription' => 'Manage customer messages and note templates',
                ]);
        }

        if (Schema::hasTable('usertypedetail')) {
            DB::table('usertypedetail')
                ->whereRaw('LOWER(TRIM(formname)) IN (?, ?)', ['message', 'customer message'])
                ->update([
                    'moduleid' => $accountModuleId,
                    'formid' => $existingId,
                    'formname' => 'Message',
                    'formdescription' => 'Manage customer messages and note templates',
                ]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('moduleheader') || !Schema::hasTable('moduledetail')) {
            return;
        }

        $accountModuleId = DB::table('moduleheader')
            ->whereRaw('LOWER(TRIM(modulename)) = ?', ['account'])
            ->value('moduleid');

        DB::table('moduledetail')
            ->whereRaw('LOWER(TRIM(formname)) = ?', ['message'])
            ->delete();

        if (!$accountModuleId) {
            return;
        }

        $remainingForms = (int) DB::table('moduledetail')
            ->where('moduleid', $accountModuleId)
            ->count();

        if ($remainingForms === 0) {
            DB::table('moduleheader')
                ->where('moduleid', $accountModuleId)
                ->delete();
        }
    }
};
