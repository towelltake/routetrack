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

        $moduleId = DB::table('moduleheader')
            ->whereRaw('LOWER(TRIM(modulename)) = ?', ['links'])
            ->value('moduleid');

        if (!$moduleId) {
            $moduleId = ((int) DB::table('moduleheader')->max('moduleid')) + 1;
            DB::table('moduleheader')->insert([
                'moduleid' => $moduleId,
                'modulename' => 'Links',
            ]);
        }

        $existing = DB::table('moduledetail')
            ->whereRaw('LOWER(TRIM(formname)) = ?', ['items group'])
            ->first();

        $description = 'Move items from one item group to another';
        $targetFormId = null;

        if ($existing) {
            DB::table('moduledetail')->where('formid', $existing->formid)->update([
                'moduleid' => $moduleId,
                'formname' => 'Items Group',
                'formdescription' => $description,
            ]);
            $targetFormId = (int) $existing->formid;
        }

        $source = DB::table('moduledetail')
            ->whereRaw('LOWER(TRIM(formname)) = ?', ['active/inactive items'])
            ->first()
            ?? DB::table('moduledetail')
                ->whereRaw('LOWER(TRIM(formname)) = ?', ['route item group'])
                ->first()
            ?? DB::table('moduledetail')
                ->whereRaw('LOWER(TRIM(formname)) = ?', ['planogram key'])
                ->first();

        $nextFormId = ((int) DB::table('moduledetail')->max('formid')) + 1;
        $nextOrder = ((int) DB::table('moduledetail')->where('moduleid', $moduleId)->max('order')) + 1;

        if ($source && $targetFormId === null) {
            $payload = (array) $source;
            $payload['formid'] = $nextFormId;
            $payload['moduleid'] = $moduleId;
            $payload['formname'] = 'Items Group';
            $payload['formdescription'] = $description;
            $payload['order'] = $nextOrder;
            DB::table('moduledetail')->insert($payload);
            $targetFormId = $nextFormId;
        }

        if ($source && $targetFormId !== null) {
            $this->syncPermissionRows('usertypedetail', 'usertypeid', (int) $source->formid, $targetFormId, $moduleId, $description);
            $this->syncPermissionRows('userdetail', 'userid', (int) $source->formid, $targetFormId, $moduleId, $description);
            return;
        }

        if ($targetFormId === null) {
            DB::table('moduledetail')->insert([
                'formid' => $nextFormId,
                'moduleid' => $moduleId,
                'formname' => 'Items Group',
                'formdescription' => $description,
                'order' => $nextOrder,
            ]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('moduledetail')) {
            return;
        }

        $formId = DB::table('moduledetail')
            ->whereRaw('LOWER(TRIM(formname)) = ?', ['items group'])
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
        string $description
    ): void {
        if (!Schema::hasTable($table)) {
            return;
        }

        $existingOwnerIds = DB::table($table)
            ->where('formid', $targetFormId)
            ->pluck($ownerColumn)
            ->map(fn ($value) => (int) $value)
            ->all();

        $rows = DB::table($table)
            ->where('formid', $sourceFormId)
            ->get()
            ->reject(fn ($row) => in_array((int) ($row->{$ownerColumn} ?? 0), $existingOwnerIds, true))
            ->map(function ($row) use ($targetFormId, $moduleId, $description) {
                $payload = (array) $row;
                unset($payload['primary_key']);
                $payload['formid'] = $targetFormId;
                $payload['moduleid'] = $moduleId;
                $payload['formname'] = 'Items Group';
                $payload['formdescription'] = $description;

                return $payload;
            })
            ->values()
            ->all();

        if ($rows) {
            DB::table($table)->insert($rows);
        }
    }
};
