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
            ->whereRaw('LOWER(TRIM(formname)) = ?', ['account customer authorize group'])
            ->value('formid');

        if (!$formId) {
            return;
        }

        DB::table('moduledetail')
            ->where('formid', $formId)
            ->update([
                'formname' => 'Account Customer Authorize Group',
                'formdescription' => 'Manage customer authorize groups',
            ]);

        if (Schema::hasTable('userdetail')) {
            DB::table('userdetail')
                ->where('formid', $formId)
                ->update([
                    'formname' => 'Account Customer Authorize Group',
                    'formdescription' => 'Manage customer authorize groups',
                ]);
        }

        if (Schema::hasTable('usertypedetail')) {
            DB::table('usertypedetail')
                ->where('formid', $formId)
                ->update([
                    'formname' => 'Account Customer Authorize Group',
                    'formdescription' => 'Manage customer authorize groups',
                ]);
        }
    }

    public function down(): void
    {
    }
};
