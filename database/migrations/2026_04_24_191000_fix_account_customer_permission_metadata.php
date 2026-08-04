<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $formId = DB::table('moduledetail')
            ->whereRaw('LOWER(TRIM(formname)) = ?', ['account customer'])
            ->value('formid');

        if (!$formId) {
            return;
        }

        $moduleId = DB::table('moduleheader')
            ->whereRaw('LOWER(TRIM(modulename)) = ?', ['account'])
            ->value('moduleid');

        DB::table('userdetail')
            ->where('formid', $formId)
            ->update([
                'formname' => 'Account Customer',
                'formdescription' => 'Manage customer masters and account setup',
                'moduleid' => $moduleId,
            ]);

        DB::table('usertypedetail')
            ->where('formid', $formId)
            ->update([
                'formname' => 'Account Customer',
                'formdescription' => 'Manage customer masters and account setup',
                'moduleid' => $moduleId,
            ]);
    }

    public function down(): void
    {
    }
};
