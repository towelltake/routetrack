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
            ->whereRaw('LOWER(TRIM(modulename)) = ?', ['scheme'])
            ->value('moduleid');

        if (! $moduleId) {
            $moduleId = ((int) DB::table('moduleheader')->max('moduleid')) + 1;

            DB::table('moduleheader')->insert([
                'moduleid' => $moduleId,
                'modulename' => 'Scheme',
            ]);
        }

        $description = 'Manage scheme promotion groups, plans, and keys';
        $order = ((int) DB::table('moduledetail')->where('moduleid', $moduleId)->max('order')) + 1;
        $existing = DB::table('moduledetail')
            ->whereRaw('LOWER(TRIM(formname)) = ?', ['promotion'])
            ->first();

        if ($existing) {
            DB::table('moduledetail')
                ->where('formid', $existing->formid)
                ->update([
                    'moduleid' => $moduleId,
                    'formname' => 'Promotion',
                    'formdescription' => $description,
                    'order' => $existing->order ?? $order,
                ]);

            return;
        }

        $source = DB::table('moduledetail')
            ->whereRaw('LOWER(TRIM(formname)) = ?', ['account tax'])
            ->first();

        $nextFormId = ((int) DB::table('moduledetail')->max('formid')) + 1;

        if ($source) {
            $payload = (array) $source;
            $payload['formid'] = $nextFormId;
            $payload['moduleid'] = $moduleId;
            $payload['formname'] = 'Promotion';
            $payload['formdescription'] = $description;
            $payload['order'] = $order;
            DB::table('moduledetail')->insert($payload);

            if (Schema::hasTable('usertypedetail')) {
                $rows = DB::table('usertypedetail')
                    ->where('formid', $source->formid)
                    ->get()
                    ->map(function ($row) use ($nextFormId, $moduleId, $description) {
                        $payload = (array) $row;
                        $payload['formid'] = $nextFormId;
                        $payload['moduleid'] = $moduleId;
                        $payload['formname'] = 'Promotion';
                        $payload['formdescription'] = $description;

                        return $payload;
                    })
                    ->all();

                if ($rows) {
                    DB::table('usertypedetail')->insert($rows);
                }
            }

            if (Schema::hasTable('userdetail')) {
                $rows = DB::table('userdetail')
                    ->where('formid', $source->formid)
                    ->get()
                    ->map(function ($row) use ($nextFormId, $moduleId, $description) {
                        $payload = (array) $row;
                        $payload['formid'] = $nextFormId;
                        $payload['moduleid'] = $moduleId;
                        $payload['formname'] = 'Promotion';
                        $payload['formdescription'] = $description;

                        return $payload;
                    })
                    ->all();

                if ($rows) {
                    DB::table('userdetail')->insert($rows);
                }
            }

            return;
        }

        DB::table('moduledetail')->insert([
            'formid' => $nextFormId,
            'moduleid' => $moduleId,
            'formname' => 'Promotion',
            'formdescription' => $description,
            'order' => $order,
        ]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('moduleheader') || !Schema::hasTable('moduledetail')) {
            return;
        }

        $formId = DB::table('moduledetail')
            ->whereRaw('LOWER(TRIM(formname)) = ?', ['promotion'])
            ->value('formid');

        if ($formId) {
            if (Schema::hasTable('userdetail')) {
                DB::table('userdetail')->where('formid', $formId)->delete();
            }

            if (Schema::hasTable('usertypedetail')) {
                DB::table('usertypedetail')->where('formid', $formId)->delete();
            }

            DB::table('moduledetail')->where('formid', $formId)->delete();
        }

        $moduleId = DB::table('moduleheader')
            ->whereRaw('LOWER(TRIM(modulename)) = ?', ['scheme'])
            ->value('moduleid');

        if (! $moduleId) {
            return;
        }

        $remainingForms = (int) DB::table('moduledetail')
            ->where('moduleid', $moduleId)
            ->count();

        if ($remainingForms === 0) {
            DB::table('moduleheader')
                ->where('moduleid', $moduleId)
                ->delete();
        }
    }
};
