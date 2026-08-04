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
            ->whereRaw('LOWER(TRIM(modulename)) = ?', ['organisation'])
            ->value('moduleid');

        if ($moduleId <= 0) {
            return;
        }

        $existing = DB::table('moduledetail')
            ->whereRaw('LOWER(TRIM(formname)) = ?', ['device registration'])
            ->first();

        $source = DB::table('moduledetail')
            ->whereRaw('LOWER(TRIM(formname)) = ?', ['van'])
            ->first()
            ?? DB::table('moduledetail')
                ->whereRaw('LOWER(TRIM(formname)) = ?', ['route category'])
                ->first();

        if (!$source) {
            return;
        }

        $description = 'Manage registered mobile devices';
        $vanOrder = (int) ($source->order ?? 0);
        $targetOrder = $vanOrder > 0 ? $vanOrder + 1 : (((int) DB::table('moduledetail')->where('moduleid', $moduleId)->max('order')) + 1);

        DB::table('moduledetail')
            ->where('moduleid', $moduleId)
            ->where('order', '>=', $targetOrder)
            ->increment('order');

        if ($existing) {
            DB::table('moduledetail')
                ->where('formid', $existing->formid)
                ->update([
                    'moduleid' => $moduleId,
                    'formname' => 'Device Registration',
                    'formdescription' => $description,
                    'order' => $targetOrder,
                ]);

            $this->syncPermissionRows('userdetail', 'userid', (int) $source->formid, (int) $existing->formid, $moduleId, 'Device Registration', $description);
            $this->syncPermissionRows('usertypedetail', 'usertypeid', (int) $source->formid, (int) $existing->formid, $moduleId, 'Device Registration', $description);

            return;
        }

        $nextFormId = ((int) DB::table('moduledetail')->max('formid')) + 1;

        $payload = (array) $source;
        unset($payload['formid']);
        $payload['formid'] = $nextFormId;
        $payload['moduleid'] = $moduleId;
        $payload['formname'] = 'Device Registration';
        $payload['formdescription'] = $description;
        $payload['order'] = $targetOrder;

        DB::table('moduledetail')->insert($payload);

        $this->syncPermissionRows('userdetail', 'userid', (int) $source->formid, $nextFormId, $moduleId, 'Device Registration', $description);
        $this->syncPermissionRows('usertypedetail', 'usertypeid', (int) $source->formid, $nextFormId, $moduleId, 'Device Registration', $description);
    }

    public function down(): void
    {
        if (!Schema::hasTable('moduledetail')) {
            return;
        }

        $formId = DB::table('moduledetail')
            ->whereRaw('LOWER(TRIM(formname)) = ?', ['device registration'])
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
