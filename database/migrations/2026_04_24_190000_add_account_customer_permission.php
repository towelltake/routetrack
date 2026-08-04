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
            ->whereRaw('LOWER(TRIM(formname)) = ?', ['account customer'])
            ->first();

        if ($existing) {
            $categoryOrder = DB::table('moduledetail')
                ->whereRaw('LOWER(TRIM(formname)) = ?', ['account customer category'])
                ->value('order');

            DB::table('moduledetail')
                ->where('formid', $existing->formid)
                ->update([
                    'moduleid' => $moduleId,
                    'formname' => 'Account Customer',
                    'formdescription' => 'Manage customer masters and account setup',
                    'order' => $categoryOrder ? ((int) $categoryOrder) + 1 : $existing->order,
                ]);

            return;
        }

        $sourceRow = DB::table('moduledetail')
            ->whereRaw('LOWER(TRIM(formname)) = ?', ['account customer category'])
            ->first();

        $sourceFormId = $sourceRow?->formid;

        $nextFormId = ((int) DB::table('moduledetail')->max('formid')) + 1;

        if ($sourceRow) {
            $payload = (array) $sourceRow;
            $payload['formid'] = $nextFormId;
            $payload['moduleid'] = $moduleId;
            $payload['formname'] = 'Account Customer';
            $payload['formdescription'] = 'Manage customer masters and account setup';
            $payload['order'] = ((int) ($sourceRow->order ?? $nextFormId)) + 1;

            DB::table('moduledetail')->insert($payload);
        } else {
            DB::table('moduledetail')->insert([
                'formid' => $nextFormId,
                'moduleid' => $moduleId,
                'formname' => 'Account Customer',
                'formdescription' => 'Manage customer masters and account setup',
                'order' => $nextFormId,
            ]);
        }

        if ($sourceFormId) {
            $userTypeRows = DB::table('usertypedetail')
                ->where('formid', $sourceFormId)
                ->get()
                ->map(function ($row) use ($nextFormId) {
                    $payload = (array) $row;
                    $payload['formid'] = $nextFormId;
                    $payload['formname'] = 'Account Customer';
                    $payload['formdescription'] = 'Manage customer masters and account setup';
                    $payload['moduleid'] = 6;

                    return $payload;
                })
                ->all();

            if ($userTypeRows) {
                DB::table('usertypedetail')->insert($userTypeRows);
            }

            $userRows = DB::table('userdetail')
                ->where('formid', $sourceFormId)
                ->get()
                ->map(function ($row) use ($nextFormId) {
                    $payload = (array) $row;
                    $payload['formid'] = $nextFormId;
                    $payload['formname'] = 'Account Customer';
                    $payload['formdescription'] = 'Manage customer masters and account setup';
                    $payload['moduleid'] = 6;

                    return $payload;
                })
                ->all();

            if ($userRows) {
                DB::table('userdetail')->insert($userRows);
            }
        }
    }

    public function down(): void
    {
        $formId = DB::table('moduledetail')
            ->whereRaw('LOWER(TRIM(formname)) = ?', ['account customer'])
            ->value('formid');

        if (!$formId) {
            return;
        }

        DB::table('userdetail')->where('formid', $formId)->delete();
        DB::table('usertypedetail')->where('formid', $formId)->delete();
        DB::table('moduledetail')->where('formid', $formId)->delete();
    }
};
