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
            ->whereRaw('LOWER(TRIM(modulename)) = ?', ['merchandizing'])
            ->value('moduleid');

        if (!$moduleId) {
            $moduleId = ((int) DB::table('moduleheader')->max('moduleid')) + 1;
            DB::table('moduleheader')->insert([
                'moduleid' => $moduleId,
                'modulename' => 'Merchandizing',
            ]);
        }

        $existing = DB::table('moduledetail')
            ->whereRaw('LOWER(TRIM(formname)) = ?', ['survey'])
            ->first();

        $description = 'Manage survey definitions and lookup rules';

        if ($existing) {
            DB::table('moduledetail')->where('formid', $existing->formid)->update([
                'moduleid' => $moduleId,
                'formname' => 'Survey',
                'formdescription' => $description,
            ]);
            return;
        }

        $source = DB::table('moduledetail')
            ->whereRaw('LOWER(TRIM(formname)) = ?', ['promotion'])
            ->first();

        $nextFormId = ((int) DB::table('moduledetail')->max('formid')) + 1;
        $nextOrder = ((int) DB::table('moduledetail')->where('moduleid', $moduleId)->max('order')) + 1;

        if ($source) {
            $payload = (array) $source;
            $payload['formid'] = $nextFormId;
            $payload['moduleid'] = $moduleId;
            $payload['formname'] = 'Survey';
            $payload['formdescription'] = $description;
            $payload['order'] = $nextOrder;
            DB::table('moduledetail')->insert($payload);

            if (Schema::hasTable('usertypedetail')) {
                $userTypeRows = DB::table('usertypedetail')
                    ->where('formid', $source->formid)
                    ->get()
                    ->map(function ($row) use ($nextFormId, $moduleId, $description) {
                        $payload = (array) $row;
                        $payload['formid'] = $nextFormId;
                        $payload['moduleid'] = $moduleId;
                        $payload['formname'] = 'Survey';
                        $payload['formdescription'] = $description;

                        return $payload;
                    })
                    ->all();

                if ($userTypeRows) {
                    DB::table('usertypedetail')->insert($userTypeRows);
                }
            }

            if (Schema::hasTable('userdetail')) {
                $userRows = DB::table('userdetail')
                    ->where('formid', $source->formid)
                    ->get()
                    ->map(function ($row) use ($nextFormId, $moduleId, $description) {
                        $payload = (array) $row;
                        $payload['formid'] = $nextFormId;
                        $payload['moduleid'] = $moduleId;
                        $payload['formname'] = 'Survey';
                        $payload['formdescription'] = $description;

                        return $payload;
                    })
                    ->all();

                if ($userRows) {
                    DB::table('userdetail')->insert($userRows);
                }
            }

            return;
        }

        DB::table('moduledetail')->insert([
            'formid' => $nextFormId,
            'moduleid' => $moduleId,
            'formname' => 'Survey',
            'formdescription' => $description,
            'order' => $nextOrder,
        ]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('moduledetail')) {
            return;
        }

        $formId = DB::table('moduledetail')
            ->whereRaw('LOWER(TRIM(formname)) = ?', ['survey'])
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
};
