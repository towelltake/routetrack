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

        if (! $moduleId) {
            return;
        }

        $existing = DB::table('moduledetail')
            ->whereRaw('LOWER(TRIM(formname)) = ?', ['account customer template'])
            ->first();

        if ($existing) {
            return;
        }

        $source = DB::table('moduledetail')
            ->whereRaw('LOWER(TRIM(formname)) = ?', ['account customer'])
            ->first();

        $nextFormId = ((int) DB::table('moduledetail')->max('formid')) + 1;

        if ($source) {
            $payload = (array) $source;
            $payload['formid'] = $nextFormId;
            $payload['moduleid'] = $moduleId;
            $payload['formname'] = 'Account Customer Template';
            $payload['formdescription'] = 'Manage reusable customer defaults and setup templates';
            DB::table('moduledetail')->insert($payload);

            $userTypeRows = DB::table('usertypedetail')
                ->where('formid', $source->formid)
                ->get()
                ->map(function ($row) use ($nextFormId, $moduleId) {
                    $payload = (array) $row;
                    $payload['formid'] = $nextFormId;
                    $payload['formname'] = 'Account Customer Template';
                    $payload['formdescription'] = 'Manage reusable customer defaults and setup templates';
                    $payload['moduleid'] = $moduleId;

                    return $payload;
                })
                ->all();

            if ($userTypeRows) {
                DB::table('usertypedetail')->insert($userTypeRows);
            }

            $userRows = DB::table('userdetail')
                ->where('formid', $source->formid)
                ->get()
                ->map(function ($row) use ($nextFormId, $moduleId) {
                    $payload = (array) $row;
                    $payload['formid'] = $nextFormId;
                    $payload['formname'] = 'Account Customer Template';
                    $payload['formdescription'] = 'Manage reusable customer defaults and setup templates';
                    $payload['moduleid'] = $moduleId;

                    return $payload;
                })
                ->all();

            if ($userRows) {
                DB::table('userdetail')->insert($userRows);
            }

            return;
        }

        DB::table('moduledetail')->insert([
            'formid' => $nextFormId,
            'moduleid' => $moduleId,
            'formname' => 'Account Customer Template',
            'formdescription' => 'Manage reusable customer defaults and setup templates',
            'order' => $nextFormId,
        ]);
    }

    public function down(): void
    {
        $formId = DB::table('moduledetail')
            ->whereRaw('LOWER(TRIM(formname)) = ?', ['account customer template'])
            ->value('formid');

        if (! $formId) {
            return;
        }

        DB::table('userdetail')->where('formid', $formId)->delete();
        DB::table('usertypedetail')->where('formid', $formId)->delete();
        DB::table('moduledetail')->where('formid', $formId)->delete();
    }
};
