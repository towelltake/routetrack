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

        $formId = DB::table('moduledetail')
            ->whereIn(DB::raw('LOWER(TRIM(formname))'), ['route', 'routes'])
            ->value('formid');

        $payload = [
            'formname' => 'Route',
            'formdescription' => 'Route',
            'moduleid' => $organisationModuleId,
            'order' => $order + 1,
        ];

        if ($formId) {
            DB::table('moduledetail')
                ->where('formid', $formId)
                ->update($payload);
        } else {
            DB::table('moduledetail')->insert($payload);
            $formId = DB::table('moduledetail')
                ->whereRaw('LOWER(TRIM(formname)) = ?', ['route'])
                ->value('formid');
        }

        if (!$formId) {
            return;
        }

        if (Schema::hasTable('userdetail')) {
            DB::table('userdetail')
                ->where('formid', $formId)
                ->update([
                    'moduleid' => $organisationModuleId,
                    'formname' => 'Route',
                    'formdescription' => 'Route',
                ]);
        }

        if (Schema::hasTable('usertypedetail')) {
            DB::table('usertypedetail')
                ->where('formid', $formId)
                ->update([
                    'moduleid' => $organisationModuleId,
                    'formname' => 'Route',
                    'formdescription' => 'Route',
                ]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('moduleheader') || !Schema::hasTable('moduledetail')) {
            return;
        }

        $operationModuleId = DB::table('moduleheader')
            ->whereRaw('LOWER(TRIM(modulename)) = ?', ['operation'])
            ->value('moduleid');

        if (!$operationModuleId) {
            return;
        }

        $formId = DB::table('moduledetail')
            ->whereRaw('LOWER(TRIM(formname)) = ?', ['route'])
            ->value('formid');

        if (!$formId) {
            return;
        }

        DB::table('moduledetail')
            ->where('formid', $formId)
            ->update([
                'moduleid' => $operationModuleId,
                'formname' => 'Routes',
                'formdescription' => 'Manage routes',
            ]);

        if (Schema::hasTable('userdetail')) {
            DB::table('userdetail')
                ->where('formid', $formId)
                ->update([
                    'moduleid' => $operationModuleId,
                    'formname' => 'Routes',
                    'formdescription' => 'Manage routes',
                ]);
        }

        if (Schema::hasTable('usertypedetail')) {
            DB::table('usertypedetail')
                ->where('formid', $formId)
                ->update([
                    'moduleid' => $operationModuleId,
                    'formname' => 'Routes',
                    'formdescription' => 'Manage routes',
                ]);
        }
    }
};
