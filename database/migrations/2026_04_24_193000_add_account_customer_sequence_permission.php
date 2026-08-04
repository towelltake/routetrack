<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $moduleId = DB::table('moduleheader')
            ->whereRaw('LOWER(TRIM(modulename)) = ?', ['account'])
            ->value('moduleid');

        if (!$moduleId) {
            return;
        }

        $existing = DB::table('moduledetail')
            ->whereRaw('LOWER(TRIM(formname)) = ?', ['account customer sequence'])
            ->first();

        if ($existing) {
            return;
        }

        $sourceRow = DB::table('moduledetail')
            ->whereRaw('LOWER(TRIM(formname)) = ?', ['account customer'])
            ->first();

        $nextFormId = ((int) DB::table('moduledetail')->max('formid')) + 1;

        if ($sourceRow) {
            $payload = (array) $sourceRow;
            $payload['formid'] = $nextFormId;
            $payload['moduleid'] = $moduleId;
            $payload['formname'] = 'Account Customer Sequence';
            $payload['formdescription'] = 'Manage customer visit planning and sequence setup';
            DB::table('moduledetail')->insert($payload);

            $userRows = DB::table('userdetail')->where('formid', $sourceRow->formid)->get();
            foreach ($userRows as $row) {
                $copy = (array) $row;
                $copy['formid'] = $nextFormId;
                $copy['moduleid'] = $moduleId;
                $copy['formname'] = 'Account Customer Sequence';
                $copy['formdescription'] = 'Manage customer visit planning and sequence setup';
                DB::table('userdetail')->insert($copy);
            }
        } else {
            DB::table('moduledetail')->insert([
                'formid' => $nextFormId,
                'moduleid' => $moduleId,
                'formname' => 'Account Customer Sequence',
                'formdescription' => 'Manage customer visit planning and sequence setup',
                'order' => $nextFormId,
            ]);
        }
    }

    public function down(): void
    {
        $formId = DB::table('moduledetail')
            ->whereRaw('LOWER(TRIM(formname)) = ?', ['account customer sequence'])
            ->value('formid');

        if (!$formId) {
            return;
        }

        DB::table('userdetail')->where('formid', $formId)->delete();
        DB::table('usertypedetail')->where('formid', $formId)->delete();
        DB::table('moduledetail')->where('formid', $formId)->delete();
    }
};
