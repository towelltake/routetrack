<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const FORMS = [
        'Analytics Overview' => 'Access the executive analytics dashboard',
        'Sales Analytics' => 'Access the sales analytics dashboard',
        'Collection Analytics' => 'Access the collection analytics dashboard',
        'Inventory Analytics' => 'Access the inventory analytics dashboard',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('moduleheader') || !Schema::hasTable('moduledetail')) {
            return;
        }

        $moduleId = (int) DB::table('moduleheader')
            ->whereRaw('LOWER(TRIM(modulename)) = ?', ['analytics'])
            ->value('moduleid');

        if ($moduleId <= 0) {
            $moduleId = ((int) DB::table('moduleheader')->max('moduleid')) + 1;

            DB::table('moduleheader')->insert([
                'moduleid' => $moduleId,
                'modulename' => 'Analytics',
            ]);
        }

        $source = DB::table('moduledetail')
            ->whereRaw('LOWER(TRIM(formname)) = ?', ['invoice'])
            ->first();

        if (!$source) {
            $source = DB::table('moduledetail')
                ->whereRaw('LOWER(TRIM(formname)) = ?', ['sales order'])
                ->first();
        }

        if (!$source) {
            return;
        }

        foreach (self::FORMS as $formName => $description) {
            $this->upsertPermission($moduleId, (int) $source->formid, $formName, $description);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('moduledetail')) {
            return;
        }

        foreach (array_keys(self::FORMS) as $formName) {
            $formId = DB::table('moduledetail')
                ->whereRaw('LOWER(TRIM(formname)) = ?', [strtolower($formName)])
                ->value('formid');

            if (!$formId) {
                continue;
            }

            if (Schema::hasTable('userdetail')) {
                DB::table('userdetail')->where('formid', $formId)->delete();
            }

            if (Schema::hasTable('usertypedetail')) {
                DB::table('usertypedetail')->where('formid', $formId)->delete();
            }

            DB::table('moduledetail')->where('formid', $formId)->delete();
        }
    }

    private function upsertPermission(int $moduleId, int $sourceFormId, string $formName, string $description): void
    {
        $existing = DB::table('moduledetail')
            ->whereRaw('LOWER(TRIM(formname)) = ?', [strtolower($formName)])
            ->first();

        if ($existing) {
            DB::table('moduledetail')
                ->where('formid', $existing->formid)
                ->update([
                    'moduleid' => $moduleId,
                    'formname' => $formName,
                    'formdescription' => $description,
                ]);

            $this->syncPermissionRows('userdetail', 'userid', $sourceFormId, (int) $existing->formid, $moduleId, $formName, $description);
            $this->syncPermissionRows('usertypedetail', 'usertypeid', $sourceFormId, (int) $existing->formid, $moduleId, $formName, $description);
            return;
        }

        $nextFormId = ((int) DB::table('moduledetail')->max('formid')) + 1;
        $nextOrder = ((int) DB::table('moduledetail')->where('moduleid', $moduleId)->max('order')) + 1;
        if ($nextOrder <= 0) {
            $nextOrder = 1;
        }

        $source = (array) DB::table('moduledetail')->where('formid', $sourceFormId)->first();
        unset($source['formid']);

        $source['formid'] = $nextFormId;
        $source['moduleid'] = $moduleId;
        $source['formname'] = $formName;
        $source['formdescription'] = $description;
        $source['order'] = $nextOrder;

        DB::table('moduledetail')->insert($source);

        $this->syncPermissionRows('userdetail', 'userid', $sourceFormId, $nextFormId, $moduleId, $formName, $description);
        $this->syncPermissionRows('usertypedetail', 'usertypeid', $sourceFormId, $nextFormId, $moduleId, $formName, $description);
    }

    private function syncPermissionRows(
        string $table,
        string $ownerColumn,
        int $sourceFormId,
        int $targetFormId,
        int $moduleId,
        string $formName,
        string $description
    ): void {
        if (!Schema::hasTable($table)) {
            return;
        }

        $sourceRows = DB::table($table)
            ->where('formid', $sourceFormId)
            ->get();

        foreach ($sourceRows as $row) {
            $payload = (array) $row;
            unset($payload['primary_key']);

            $ownerId = (int) ($row->{$ownerColumn} ?? 0);
            $payload['formid'] = $targetFormId;
            $payload['moduleid'] = $moduleId;
            $payload['formname'] = $formName;
            $payload['formdescription'] = $description;

            $exists = DB::table($table)
                ->where($ownerColumn, $ownerId)
                ->where('formid', $targetFormId)
                ->exists();

            if ($exists) {
                DB::table($table)
                    ->where($ownerColumn, $ownerId)
                    ->where('formid', $targetFormId)
                    ->update($payload);
                continue;
            }

            DB::table($table)->insert($payload);
        }
    }
};
