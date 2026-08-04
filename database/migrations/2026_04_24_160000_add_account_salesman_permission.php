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

        $accountModuleId = DB::table('moduleheader')
            ->whereRaw('LOWER(TRIM(modulename)) = ?', ['account'])
            ->value('moduleid');

        if (!$accountModuleId) {
            return;
        }

        $formId = DB::table('moduledetail')
            ->whereRaw('LOWER(TRIM(formname)) = ?', ['account salesman'])
            ->value('formid');

        $order = (int) DB::table('moduledetail')
            ->where('moduleid', $accountModuleId)
            ->max('order');

        if (!$formId) {
            DB::table('moduledetail')->insert([
                'formname' => 'Account Salesman',
                'formdescription' => 'Manage salesman profiles, assignments, and device access',
                'moduleid' => $accountModuleId,
                'order' => $order + 1,
            ]);

            $formId = DB::table('moduledetail')
                ->whereRaw('LOWER(TRIM(formname)) = ?', ['account salesman'])
                ->value('formid');
        } else {
            DB::table('moduledetail')
                ->where('formid', $formId)
                ->update([
                    'formname' => 'Account Salesman',
                    'formdescription' => 'Manage salesman profiles, assignments, and device access',
                    'moduleid' => $accountModuleId,
                ]);
        }

        if (!$formId) {
            return;
        }

        $sourceFormId = DB::table('moduledetail')
            ->whereRaw('LOWER(TRIM(formname)) = ?', ['customer message'])
            ->value('formid');

        if (!$sourceFormId) {
            return;
        }

        if (Schema::hasTable('usertypedetail')) {
            $typeRows = DB::table('usertypedetail')
                ->where('formid', $sourceFormId)
                ->get();

            foreach ($typeRows as $row) {
                $exists = DB::table('usertypedetail')
                    ->where('usertypeid', $row->usertypeid)
                    ->where('formid', $formId)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $payload = (array) $row;
                unset($payload['id']);
                $payload['formid'] = $formId;
                $payload['formname'] = 'Account Salesman';
                $payload['formdescription'] = 'Manage salesman profiles, assignments, and device access';
                $payload['moduleid'] = $accountModuleId;

                DB::table('usertypedetail')->insert($payload);
            }
        }

        if (Schema::hasTable('userdetail')) {
            $userRows = DB::table('userdetail')
                ->where('formid', $sourceFormId)
                ->get();

            foreach ($userRows as $row) {
                $exists = DB::table('userdetail')
                    ->where('userid', $row->userid)
                    ->where('formid', $formId)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $payload = (array) $row;
                unset($payload['id']);
                $payload['formid'] = $formId;
                $payload['formname'] = 'Account Salesman';
                $payload['formdescription'] = 'Manage salesman profiles, assignments, and device access';
                $payload['moduleid'] = $accountModuleId;

                DB::table('userdetail')->insert($payload);
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('moduledetail')) {
            return;
        }

        $formId = DB::table('moduledetail')
            ->whereRaw('LOWER(TRIM(formname)) = ?', ['account salesman'])
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
