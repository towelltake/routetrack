<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const GPS_MODULE_NAME = 'GPS Routing';

    private const GPS_FORMS = [
        'customer location' => ['Customer Location', 'View customer locations on a map', 1],
        'route location' => ['Route Location', 'Last known GPS position for every route', 2],
        'route tracking' => ['Route Tracking', 'Compare planned vs actual visit routes using live GPS tracking data', 3],
        'route replay' => ['Route Replay', 'Animated playback of real recorded GPS history for a route', 4],
    ];

    public function up(): void
    {
        if (!Schema::hasTable('moduleheader') || !Schema::hasTable('moduledetail')) {
            return;
        }

        $gpsModuleId = $this->gpsModuleId();

        foreach (self::GPS_FORMS as $lookupName => [$formName, $description, $order]) {
            $formId = DB::table('moduledetail')
                ->whereRaw('LOWER(TRIM(formname)) = ?', [$lookupName])
                ->value('formid');

            if (!$formId) {
                continue;
            }

            DB::table('moduledetail')
                ->where('formid', $formId)
                ->update([
                    'moduleid' => $gpsModuleId,
                    'formname' => $formName,
                    'formdescription' => $description,
                    'order' => $order,
                ]);

            $this->syncPermissionModule('userdetail', (int) $formId, $gpsModuleId, $formName, $description);
            $this->syncPermissionModule('usertypedetail', (int) $formId, $gpsModuleId, $formName, $description);
        }

        $this->removeEmptyLegacyModules();
    }

    public function down(): void
    {
        if (!Schema::hasTable('moduleheader') || !Schema::hasTable('moduledetail')) {
            return;
        }

        foreach (self::GPS_FORMS as $lookupName => [$formName, $description]) {
            $moduleId = $this->moduleIdFor($formName);
            $formId = DB::table('moduledetail')
                ->whereRaw('LOWER(TRIM(formname)) = ?', [$lookupName])
                ->value('formid');

            if (!$formId) {
                continue;
            }

            DB::table('moduledetail')
                ->where('formid', $formId)
                ->update([
                    'moduleid' => $moduleId,
                    'formname' => $formName,
                    'formdescription' => $description,
                    'order' => 1,
                ]);

            $this->syncPermissionModule('userdetail', (int) $formId, $moduleId, $formName, $description);
            $this->syncPermissionModule('usertypedetail', (int) $formId, $moduleId, $formName, $description);
        }

        $gpsModuleId = (int) DB::table('moduleheader')
            ->whereRaw('LOWER(TRIM(modulename)) = ?', [strtolower(self::GPS_MODULE_NAME)])
            ->value('moduleid');

        if ($gpsModuleId > 0 && !DB::table('moduledetail')->where('moduleid', $gpsModuleId)->exists()) {
            DB::table('moduleheader')->where('moduleid', $gpsModuleId)->delete();
        }
    }

    private function gpsModuleId(): int
    {
        return $this->moduleIdFor(self::GPS_MODULE_NAME);
    }

    private function moduleIdFor(string $moduleName): int
    {
        $moduleId = (int) DB::table('moduleheader')
            ->whereRaw('LOWER(TRIM(modulename)) = ?', [strtolower($moduleName)])
            ->value('moduleid');

        if ($moduleId > 0) {
            return $moduleId;
        }

        $moduleId = ((int) DB::table('moduleheader')->max('moduleid')) + 1;

        DB::table('moduleheader')->insert([
            'moduleid' => $moduleId,
            'modulename' => $moduleName,
        ]);

        return $moduleId;
    }

    private function syncPermissionModule(
        string $table,
        int $formId,
        int $moduleId,
        string $formName,
        string $description
    ): void {
        if (!Schema::hasTable($table)) {
            return;
        }

        DB::table($table)
            ->where('formid', $formId)
            ->update([
                'moduleid' => $moduleId,
                'formname' => $formName,
                'formdescription' => $description,
            ]);
    }

    private function removeEmptyLegacyModules(): void
    {
        foreach (array_keys(self::GPS_FORMS) as $moduleName) {
            $moduleId = (int) DB::table('moduleheader')
                ->whereRaw('LOWER(TRIM(modulename)) = ?', [$moduleName])
                ->value('moduleid');

            if ($moduleId <= 0) {
                continue;
            }

            if (!DB::table('moduledetail')->where('moduleid', $moduleId)->exists()) {
                DB::table('moduleheader')->where('moduleid', $moduleId)->delete();
            }
        }
    }
};
