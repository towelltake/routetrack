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

        $moduleId = DB::table('moduleheader')
            ->whereRaw('LOWER(TRIM(modulename)) = ?', ['account'])
            ->value('moduleid');

        if (! $moduleId) {
            return;
        }

        $existing = DB::table('moduledetail')
            ->whereRaw('LOWER(TRIM(formname)) = ?', ['account auto jp management'])
            ->first();

        if ($existing) {
            return;
        }

        $sourceRow = DB::table('moduledetail')
            ->whereRaw('LOWER(TRIM(formname)) = ?', ['account customer sequence'])
            ->first();

        $nextFormId = ((int) DB::table('moduledetail')->max('formid')) + 1;

        if ($sourceRow) {
            $payload = (array) $sourceRow;
            $payload['formid'] = $nextFormId;
            $payload['moduleid'] = $moduleId;
            $payload['formname'] = 'Account Auto JP Management';
            $payload['formdescription'] = 'Generate, review, and publish automatic journey plans by route and week';
            DB::table('moduledetail')->insert($payload);

            $userRows = DB::table('userdetail')->where('formid', $sourceRow->formid)->get();
            foreach ($userRows as $row) {
                $copy = (array) $row;
                $copy['formid'] = $nextFormId;
                $copy['moduleid'] = $moduleId;
                $copy['formname'] = 'Account Auto JP Management';
                $copy['formdescription'] = 'Generate, review, and publish automatic journey plans by route and week';
                DB::table('userdetail')->insert($copy);
            }
        } else {
            DB::table('moduledetail')->insert([
                'formid' => $nextFormId,
                'moduleid' => $moduleId,
                'formname' => 'Account Auto JP Management',
                'formdescription' => 'Generate, review, and publish automatic journey plans by route and week',
                'order' => $nextFormId,
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('moduledetail')) {
            return;
        }

        $formId = DB::table('moduledetail')
            ->whereRaw('LOWER(TRIM(formname)) = ?', ['account auto jp management'])
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
};
