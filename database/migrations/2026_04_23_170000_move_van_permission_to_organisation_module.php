<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $organisationModuleId = DB::table('moduleheader')
            ->whereRaw('LOWER(TRIM(modulename)) = ?', ['organisation'])
            ->value('moduleid');

        if (!$organisationModuleId) {
            return;
        }

        $formId = DB::table('moduledetail')
            ->whereIn(DB::raw('LOWER(TRIM(formname))'), ['vehicle', 'van'])
            ->value('formid');

        if (!$formId) {
            return;
        }

        DB::table('moduledetail')
            ->where('formid', $formId)
            ->update([
                'formname' => 'Van',
                'formdescription' => 'Van',
                'moduleid' => $organisationModuleId,
            ]);

        DB::table('userdetail')
            ->where('formid', $formId)
            ->update([
                'moduleid' => $organisationModuleId,
                'formname' => 'Van',
                'formdescription' => 'Van',
            ]);

        DB::table('usertypedetail')
            ->where('formid', $formId)
            ->update([
                'moduleid' => $organisationModuleId,
                'formname' => 'Van',
                'formdescription' => 'Van',
            ]);
    }

    public function down(): void
    {
        $operationModuleId = DB::table('moduleheader')
            ->whereRaw('LOWER(TRIM(modulename)) = ?', ['operation'])
            ->value('moduleid');

        if (!$operationModuleId) {
            return;
        }

        $formId = DB::table('moduledetail')
            ->whereRaw('LOWER(TRIM(formname)) = ?', ['van'])
            ->value('formid');

        if (!$formId) {
            return;
        }

        DB::table('moduledetail')
            ->where('formid', $formId)
            ->update([
                'moduleid' => $operationModuleId,
                'formname' => 'Vehicle',
                'formdescription' => 'Vehicle',
            ]);

        DB::table('userdetail')
            ->where('formid', $formId)
            ->update([
                'moduleid' => $operationModuleId,
                'formname' => 'Vehicle',
                'formdescription' => 'Vehicle',
            ]);

        DB::table('usertypedetail')
            ->where('formid', $formId)
            ->update([
                'moduleid' => $operationModuleId,
                'formname' => 'Vehicle',
                'formdescription' => 'Vehicle',
            ]);
    }
};
