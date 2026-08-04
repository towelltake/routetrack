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

        $moduleId = $this->ensureOrganisationModule();
        if ($moduleId <= 0) {
            return;
        }

        $description = 'Manage registered mobile devices';
        $source = $this->sourceForm($moduleId);
        $targetOrder = $this->targetOrder($moduleId, $source);

        $existing = DB::table('moduledetail')
            ->whereRaw('LOWER(TRIM(formname)) = ?', ['device registration'])
            ->first();

        if ($existing) {
            DB::table('moduledetail')
                ->where('formid', $existing->formid)
                ->update([
                    'moduleid' => $moduleId,
                    'formname' => 'Device Registration',
                    'formdescription' => $description,
                    'order' => $targetOrder,
                ]);

            $targetFormId = (int) $existing->formid;
        } else {
            $targetFormId = ((int) DB::table('moduledetail')->max('formid')) + 1;

            if ($source) {
                $payload = (array) $source;
                unset($payload['formid']);
                $payload['formid'] = $targetFormId;
                $payload['moduleid'] = $moduleId;
                $payload['formname'] = 'Device Registration';
                $payload['formdescription'] = $description;
                $payload['order'] = $targetOrder;

                DB::table('moduledetail')->insert($payload);
            } else {
                DB::table('moduledetail')->insert([
                    'formid' => $targetFormId,
                    'moduleid' => $moduleId,
                    'formname' => 'Device Registration',
                    'formdescription' => $description,
                    'order' => $targetOrder,
                ]);
            }
        }

        if ($source) {
            $this->syncPermissionRows('userdetail', 'userid', (int) $source->formid, $targetFormId, $moduleId, 'Device Registration', $description);
            $this->syncPermissionRows('usertypedetail', 'usertypeid', (int) $source->formid, $targetFormId, $moduleId, 'Device Registration', $description);
        }

        $this->refreshExistingPermissionMetadata('userdetail', $targetFormId, $moduleId, 'Device Registration', $description);
        $this->refreshExistingPermissionMetadata('usertypedetail', $targetFormId, $moduleId, 'Device Registration', $description);
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

    private function ensureOrganisationModule(): int
    {
        $moduleId = (int) DB::table('moduleheader')
            ->whereRaw('LOWER(TRIM(modulename)) = ?', ['organisation'])
            ->value('moduleid');

        if ($moduleId > 0) {
            return $moduleId;
        }

        $moduleId = ((int) DB::table('moduleheader')->max('moduleid')) + 1;

        DB::table('moduleheader')->insert([
            'moduleid' => $moduleId,
            'modulename' => 'Organisation',
        ]);

        return $moduleId;
    }

    private function sourceForm(int $moduleId): ?object
    {
        foreach (['van', 'route category', 'route', 'country'] as $name) {
            $row = DB::table('moduledetail')
                ->whereRaw('LOWER(TRIM(formname)) = ?', [$name])
                ->first();

            if ($row) {
                return $row;
            }
        }

        return DB::table('moduledetail')
            ->where('moduleid', $moduleId)
            ->orderBy('order')
            ->first();
    }

    private function targetOrder(int $moduleId, ?object $source): int
    {
        $sourceOrder = (int) ($source->order ?? 0);
        if ($sourceOrder > 0) {
            return $sourceOrder + 1;
        }

        $nextOrder = ((int) DB::table('moduledetail')->where('moduleid', $moduleId)->max('order')) + 1;

        return $nextOrder > 0 ? $nextOrder : 1;
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
        if (!Schema::hasTable($table) || $sourceFormId <= 0) {
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
            } else {
                DB::table($table)->insert($payload);
            }
        }
    }

    private function refreshExistingPermissionMetadata(
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
};
