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

        $moduleId = (int) DB::table('moduleheader')->whereRaw('LOWER(TRIM(modulename)) = ?', ['reports'])->value('moduleid');
        if ($moduleId <= 0) {
            $moduleId = ((int) DB::table('moduleheader')->max('moduleid')) + 1;
            DB::table('moduleheader')->insert(['moduleid' => $moduleId, 'modulename' => 'Reports']);
        }

        $existing = DB::table('moduledetail')->whereRaw('LOWER(TRIM(formname)) = ?', ['survey tracking'])->first();
        $source = DB::table('moduledetail')->whereRaw('LOWER(TRIM(formname)) = ?', ['pos tracking'])->first()
            ?: DB::table('moduledetail')->whereRaw('LOWER(TRIM(formname)) = ?', ['sales summary'])->first();
        $description = 'Survey Tracking';

        if ($existing) {
            DB::table('moduledetail')->where('formid', $existing->formid)->update(['moduleid' => $moduleId, 'formname' => 'Survey Tracking', 'formdescription' => $description]);
            if ($source) {
                $this->syncPermissionRows('userdetail', 'userid', (int) $source->formid, (int) $existing->formid, $moduleId, 'Survey Tracking', $description);
                $this->syncPermissionRows('usertypedetail', 'usertypeid', (int) $source->formid, (int) $existing->formid, $moduleId, 'Survey Tracking', $description);
            }
            return;
        }

        $nextFormId = ((int) DB::table('moduledetail')->max('formid')) + 1;
        $nextOrder = ((int) DB::table('moduledetail')->where('moduleid', $moduleId)->max('order')) + 1;
        if ($nextOrder <= 0) {
            $nextOrder = 1;
        }

        if ($source) {
            $payload = (array) $source;
            unset($payload['formid']);
            $payload['formid'] = $nextFormId;
            $payload['moduleid'] = $moduleId;
            $payload['formname'] = 'Survey Tracking';
            $payload['formdescription'] = $description;
            $payload['order'] = $nextOrder;
            DB::table('moduledetail')->insert($payload);
            $this->syncPermissionRows('userdetail', 'userid', (int) $source->formid, $nextFormId, $moduleId, 'Survey Tracking', $description);
            $this->syncPermissionRows('usertypedetail', 'usertypeid', (int) $source->formid, $nextFormId, $moduleId, 'Survey Tracking', $description);
            return;
        }

        DB::table('moduledetail')->insert(['formid' => $nextFormId, 'moduleid' => $moduleId, 'formname' => 'Survey Tracking', 'formdescription' => $description, 'order' => $nextOrder]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('moduledetail')) {
            return;
        }

        $formId = DB::table('moduledetail')->whereRaw('LOWER(TRIM(formname)) = ?', ['survey tracking'])->value('formid');
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

    private function syncPermissionRows(string $table, string $ownerColumn, int $sourceFormId, int $targetFormId, int $moduleId, string $formName, string $description): void
    {
        if (!Schema::hasTable($table)) {
            return;
        }

        $sourceRows = DB::table($table)->where('formid', $sourceFormId)->get();
        foreach ($sourceRows as $row) {
            $payload = (array) $row;
            unset($payload['primary_key']);
            $ownerId = (int) ($row->{$ownerColumn} ?? 0);
            $payload['formid'] = $targetFormId;
            $payload['moduleid'] = $moduleId;
            $payload['formname'] = $formName;
            $payload['formdescription'] = $description;
            $exists = DB::table($table)->where($ownerColumn, $ownerId)->where('formid', $targetFormId)->exists();
            if ($exists) {
                DB::table($table)->where($ownerColumn, $ownerId)->where('formid', $targetFormId)->update($payload);
            } else {
                DB::table($table)->insert($payload);
            }
        }
    }
};
