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

        $moduleId = (int) DB::table('moduleheader')
            ->whereRaw('LOWER(TRIM(modulename)) = ?', ['inventory'])
            ->value('moduleid');

        if ($moduleId <= 0) {
            return;
        }

        $existing = DB::table('moduledetail')
            ->whereRaw('LOWER(TRIM(formname)) = ?', ['target & commission'])
            ->first();

        $source = DB::table('moduledetail')
            ->whereRaw('LOWER(TRIM(formname)) = ?', ['delivery'])
            ->first()
            ?? DB::table('moduledetail')
                ->whereRaw('LOWER(TRIM(formname)) = ?', ['daily salesman load'])
                ->first();

        $description = 'Manage route target and commission periods';

        if ($existing) {
            DB::table('moduledetail')
                ->where('formid', $existing->formid)
                ->update([
                    'moduleid' => $moduleId,
                    'formname' => 'Target & Commission',
                    'formdescription' => $description,
                ]);

            if ($source) {
                $this->syncPermissionRows('userdetail', 'userid', (int) $source->formid, (int) $existing->formid, $moduleId, 'Target & Commission', $description);
                $this->syncPermissionRows('usertypedetail', 'usertypeid', (int) $source->formid, (int) $existing->formid, $moduleId, 'Target & Commission', $description);
            }

            return;
        }

        if (!$source) {
            return;
        }

        $nextFormId = ((int) DB::table('moduledetail')->max('formid')) + 1;
        $nextOrder = ((int) DB::table('moduledetail')->where('moduleid', $moduleId)->max('order')) + 1;
        if ($nextOrder <= 0) {
            $nextOrder = 1;
        }

        $payload = (array) $source;
        unset($payload['formid']);
        $payload['formid'] = $nextFormId;
        $payload['moduleid'] = $moduleId;
        $payload['formname'] = 'Target & Commission';
        $payload['formdescription'] = $description;
        $payload['order'] = $nextOrder;

        DB::table('moduledetail')->insert($payload);

        $this->syncPermissionRows('userdetail', 'userid', (int) $source->formid, $nextFormId, $moduleId, 'Target & Commission', $description);
        $this->syncPermissionRows('usertypedetail', 'usertypeid', (int) $source->formid, $nextFormId, $moduleId, 'Target & Commission', $description);
    }

    public function down(): void
    {
        if (!Schema::hasTable('moduledetail')) {
            return;
        }

        $formId = DB::table('moduledetail')
            ->whereRaw('LOWER(TRIM(formname)) = ?', ['target & commission'])
            ->value('formid');

        if (!$formId) {
            return;
        }

        if (Schema::hasTable('userdetail')) {
            DB::table('userdetail')->where('formid', $formId)->delete();
        }

        if (Schema::hasTable('usertypedetail')) {
            DB::table('usertypedetail')->where('formid', $formId)->delete();
        }

        DB::table('moduledetail')->where('formid', $formId)->delete();
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
