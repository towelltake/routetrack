<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $organisationModuleId = DB::table('moduleheader')
            ->whereRaw('LOWER(modulename) = ?', ['organisation'])
            ->value('moduleid');

        if (!$organisationModuleId) {
            return;
        }

        $formIds = DB::table('moduledetail')
            ->whereIn(DB::raw('LOWER(TRIM(formname))'), ['route setting template', 'route template'])
            ->pluck('formid');

        if ($formIds->isEmpty()) {
            return;
        }

        DB::table('moduledetail')
            ->whereIn('formid', $formIds)
            ->update([
                'moduleid' => $organisationModuleId,
                'formname' => 'Route Template',
                'formdescription' => 'Manage route templates',
            ]);

        DB::table('userdetail')
            ->whereIn('formid', $formIds)
            ->update([
                'moduleid' => $organisationModuleId,
                'formname' => 'Route Template',
                'formdescription' => 'Manage route templates',
            ]);

        DB::table('usertypedetail')
            ->whereIn('formid', $formIds)
            ->update([
                'moduleid' => $organisationModuleId,
                'formname' => 'Route Template',
                'formdescription' => 'Manage route templates',
            ]);
    }

    public function down(): void
    {
        $operationModuleId = DB::table('moduleheader')
            ->whereRaw('LOWER(modulename) = ?', ['operation'])
            ->value('moduleid');

        if (!$operationModuleId) {
            return;
        }

        $formIds = DB::table('moduledetail')
            ->whereRaw('LOWER(TRIM(formname)) = ?', ['route template'])
            ->pluck('formid');

        if ($formIds->isEmpty()) {
            return;
        }

        DB::table('moduledetail')
            ->whereIn('formid', $formIds)
            ->update([
                'moduleid' => $operationModuleId,
                'formname' => 'Route Setting Template',
                'formdescription' => 'Manage route setting templates',
            ]);

        DB::table('userdetail')
            ->whereIn('formid', $formIds)
            ->update([
                'moduleid' => $operationModuleId,
                'formname' => 'Route Setting Template',
                'formdescription' => 'Manage route setting templates',
            ]);

        DB::table('usertypedetail')
            ->whereIn('formid', $formIds)
            ->update([
                'moduleid' => $operationModuleId,
                'formname' => 'Route Setting Template',
                'formdescription' => 'Manage route setting templates',
            ]);
    }
};
