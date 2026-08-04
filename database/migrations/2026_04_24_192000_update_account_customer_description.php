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

        $description = 'Manage customer masters and account setup';

        DB::table('moduledetail')
            ->where('formid', $formId)
            ->update(['formdescription' => $description]);

        DB::table('userdetail')
            ->where('formid', $formId)
            ->update(['formdescription' => $description]);

        DB::table('usertypedetail')
            ->where('formid', $formId)
            ->update(['formdescription' => $description]);
    }

    public function down(): void
    {
        $formId = DB::table('moduledetail')
            ->whereRaw('LOWER(TRIM(formname)) = ?', ['account customer'])
            ->value('formid');

        if (!$formId) {
            return;
        }

        $description = 'Manage account customers with the legacy workflow';

        DB::table('moduledetail')
            ->where('formid', $formId)
            ->update(['formdescription' => $description]);

        DB::table('userdetail')
            ->where('formid', $formId)
            ->update(['formdescription' => $description]);

        DB::table('usertypedetail')
            ->where('formid', $formId)
            ->update(['formdescription' => $description]);
    }
};
