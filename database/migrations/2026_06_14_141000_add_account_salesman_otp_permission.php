<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('moduleheader') || ! Schema::hasTable('moduledetail')) {
            return;
        }

        $moduleId = (int) DB::table('moduleheader')
            ->whereRaw('LOWER(TRIM(modulename)) = ?', ['account'])
            ->value('moduleid');

        if ($moduleId <= 0) {
            return;
        }

        $formName = 'Salesman OTP';
        $description = 'Generate OTP overrides for salesman access';
        $source = DB::table('moduledetail')
            ->whereRaw('LOWER(TRIM(formname)) = ?', ['account salesman'])
            ->first()
            ?: DB::table('moduledetail')
                ->whereRaw('LOWER(TRIM(formname)) = ?', ['account customer'])
                ->first();

        $existing = DB::table('moduledetail')
            ->whereRaw('LOWER(TRIM(formname)) = ?', ['salesman otp'])
            ->first();

        $targetFormId = $existing ? (int) $existing->formid : ((int) DB::table('moduledetail')->max('formid')) + 1;
        $targetOrder = $existing
            ? (int) $existing->order
            : (((int) DB::table('moduledetail')->where('moduleid', $moduleId)->max('order')) + 1);

        if ($existing) {
            DB::table('moduledetail')->where('formid', $targetFormId)->update([
                'moduleid' => $moduleId,
                'formname' => $formName,
                'formdescription' => $description,
                'order' => $targetOrder,
            ]);
        } elseif ($source) {
            $payload = (array) $source;
            unset($payload['formid']);
            $payload['formid'] = $targetFormId;
            $payload['moduleid'] = $moduleId;
            $payload['formname'] = $formName;
            $payload['formdescription'] = $description;
            $payload['order'] = $targetOrder;

            DB::table('moduledetail')->insert($payload);
        } else {
            DB::table('moduledetail')->insert([
                'formid' => $targetFormId,
                'moduleid' => $moduleId,
                'formname' => $formName,
                'formdescription' => $description,
                'order' => $targetOrder > 0 ? $targetOrder : 1,
            ]);
        }

        if ($source) {
            $this->syncPermissionRows('userdetail', 'userid', (int) $source->formid, $targetFormId, $moduleId, $formName, $description);
            $this->syncPermissionRows('usertypedetail', 'usertypeid', (int) $source->formid, $targetFormId, $moduleId, $formName, $description);
        }

        $this->refreshPermissionMetadata('userdetail', $targetFormId, $moduleId, $formName, $description);
        $this->refreshPermissionMetadata('usertypedetail', $targetFormId, $moduleId, $formName, $description);
    }

    public function down(): void
    {
        if (! Schema::hasTable('moduledetail')) {
            return;
        }

        $formId = DB::table('moduledetail')
            ->whereRaw('LOWER(TRIM(formname)) = ?', ['salesman otp'])
            ->value('formid');

        if (! $formId) {
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

    private function syncPermissionRows(string $table, string $ownerColumn, int $sourceFormId, int $targetFormId, int $moduleId, string $formName, string $description): void
    {
        if (! Schema::hasTable($table) || $sourceFormId <= 0) {
            return;
        }

        $rows = DB::table($table)->where('formid', $sourceFormId)->get();

        foreach ($rows as $row) {
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
            } else {
                DB::table($table)->insert($payload);
            }
        }
    }

    private function refreshPermissionMetadata(string $table, int $formId, int $moduleId, string $formName, string $description): void
    {
        if (! Schema::hasTable($table)) {
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
};
