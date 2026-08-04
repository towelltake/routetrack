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

        $formId = DB::table('moduledetail')
            ->whereRaw('LOWER(TRIM(formname)) = ?', ['account salesman'])
            ->value('formid');

        if (!$formId) {
            return;
        }

        $moduleId = DB::table('moduleheader')
            ->whereRaw('LOWER(TRIM(modulename)) = ?', ['account'])
            ->value('moduleid');

        DB::table('moduledetail')
            ->where('formid', $formId)
            ->update([
                'formname' => 'Account Salesman',
                'formdescription' => 'Manage salesman profiles and device access',
                'moduleid' => $moduleId,
            ]);

        if (Schema::hasTable('userdetail')) {
            DB::table('userdetail')
                ->where('formid', $formId)
                ->update([
                    'formname' => 'Account Salesman',
                    'formdescription' => 'Manage salesman profiles and device access',
                    'moduleid' => $moduleId,
                ]);
        }

        if (Schema::hasTable('usertypedetail')) {
            DB::table('usertypedetail')
                ->where('formid', $formId)
                ->update([
                    'formname' => 'Account Salesman',
                    'formdescription' => 'Manage salesman profiles and device access',
                    'moduleid' => $moduleId,
                ]);
        }
    }

    public function down(): void
    {
    }
};
