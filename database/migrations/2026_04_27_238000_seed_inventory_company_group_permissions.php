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

        $target = DB::table('moduledetail')
            ->whereRaw('LOWER(TRIM(formname)) = ?', ['company group'])
            ->first();

        $source = DB::table('moduledetail')
            ->whereRaw('LOWER(TRIM(formname)) = ?', ['company'])
            ->first();

        if (!$target || !$source) {
            return;
        }

        $description = 'Manage company groups';

        DB::table('moduledetail')
            ->where('formid', $target->formid)
            ->update([
                'moduleid' => $target->moduleid,
                'formname' => 'Company Group',
                'formdescription' => $description,
            ]);

        $this->syncPermissionRows(
            'userdetail',
            'userid',
            (int) $source->formid,
            (int) $target->formid,
            (int) $target->moduleid,
            'Company Group',
            $description
        );

        $this->syncPermissionRows(
            'usertypedetail',
            'usertypeid',
            (int) $source->formid,
            (int) $target->formid,
            (int) $target->moduleid,
            'Company Group',
            $description
        );
    }

    public function down(): void
    {
        // No-op: this migration normalizes existing permission rows in place.
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
