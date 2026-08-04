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
            ->whereRaw('LOWER(TRIM(formname)) = ?', ['account customer template'])
            ->value('formid');

        if (!$formId) {
            return;
        }

        $description = 'Manage customer defaults and templates';

        DB::table('moduledetail')
            ->where('formid', $formId)
            ->update([
                'formname' => 'Account Customer Template',
                'formdescription' => $description,
            ]);

        if (Schema::hasTable('userdetail')) {
            DB::table('userdetail')
                ->where('formid', $formId)
                ->update([
                    'formname' => 'Account Customer Template',
                    'formdescription' => $description,
                ]);
        }

        if (Schema::hasTable('usertypedetail')) {
            DB::table('usertypedetail')
                ->where('formid', $formId)
                ->update([
                    'formname' => 'Account Customer Template',
                    'formdescription' => $description,
                ]);
        }
    }

    public function down(): void
    {
    }
};
