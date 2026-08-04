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

        $customerFormId = DB::table('moduledetail')
            ->whereRaw('LOWER(TRIM(formname)) = ?', ['message'])
            ->value('formid');

        if ($customerFormId) {
            DB::table('moduledetail')
                ->where('formid', $customerFormId)
                ->update([
                    'formname' => 'Customer Message',
                    'formdescription' => 'Manage customer messages and note templates',
                    'moduleid' => $accountModuleId,
                ]);

            if (Schema::hasTable('userdetail')) {
                DB::table('userdetail')
                    ->where('formid', $customerFormId)
                    ->update([
                        'formname' => 'Customer Message',
                        'formdescription' => 'Manage customer messages and note templates',
                        'moduleid' => $accountModuleId,
                    ]);
            }

            if (Schema::hasTable('usertypedetail')) {
                DB::table('usertypedetail')
                    ->where('formid', $customerFormId)
                    ->update([
                        'formname' => 'Customer Message',
                        'formdescription' => 'Manage customer messages and note templates',
                        'moduleid' => $accountModuleId,
                    ]);
            }
        }

        $existingSalesmanFormId = DB::table('moduledetail')
            ->whereRaw('LOWER(TRIM(formname)) = ?', ['salesman message'])
            ->value('formid');

        if (!$existingSalesmanFormId) {
            $order = (int) DB::table('moduledetail')
                ->where('moduleid', $accountModuleId)
                ->max('order');

            DB::table('moduledetail')->insert([
                'formname' => 'Salesman Message',
                'formdescription' => 'Manage salesman messages and note templates',
                'moduleid' => $accountModuleId,
                'order' => $order + 1,
            ]);
            $existingSalesmanFormId = DB::table('moduledetail')
                ->whereRaw('LOWER(TRIM(formname)) = ?', ['salesman message'])
                ->value('formid');
        } else {
            DB::table('moduledetail')
                ->where('formid', $existingSalesmanFormId)
                ->update([
                    'formname' => 'Salesman Message',
                    'formdescription' => 'Manage salesman messages and note templates',
                    'moduleid' => $accountModuleId,
                ]);
        }

        if (!$customerFormId || !$existingSalesmanFormId) {
            return;
        }

        if (Schema::hasTable('usertypedetail')) {
            $typeRows = DB::table('usertypedetail')
                ->where('formid', $customerFormId)
                ->get();

            foreach ($typeRows as $row) {
                $exists = DB::table('usertypedetail')
                    ->where('usertypeid', $row->usertypeid)
                    ->where('formid', $existingSalesmanFormId)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $payload = (array) $row;
                unset($payload['id']);
                $payload['formid'] = $existingSalesmanFormId;
                $payload['formname'] = 'Salesman Message';
                $payload['formdescription'] = 'Manage salesman messages and note templates';
                $payload['moduleid'] = $accountModuleId;

                DB::table('usertypedetail')->insert($payload);
            }
        }

        if (Schema::hasTable('userdetail')) {
            $userRows = DB::table('userdetail')
                ->where('formid', $customerFormId)
                ->get();

            foreach ($userRows as $row) {
                $exists = DB::table('userdetail')
                    ->where('userid', $row->userid)
                    ->where('formid', $existingSalesmanFormId)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $payload = (array) $row;
                unset($payload['id']);
                $payload['formid'] = $existingSalesmanFormId;
                $payload['formname'] = 'Salesman Message';
                $payload['formdescription'] = 'Manage salesman messages and note templates';
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

        $messageFormId = DB::table('moduledetail')
            ->whereRaw('LOWER(TRIM(formname)) = ?', ['customer message'])
            ->value('formid');

        if ($messageFormId) {
            DB::table('moduledetail')
                ->where('formid', $messageFormId)
                ->update([
                    'formname' => 'Message',
                    'formdescription' => 'Manage customer messages and note templates',
                ]);

            if (Schema::hasTable('userdetail')) {
                DB::table('userdetail')
                    ->where('formid', $messageFormId)
                    ->update([
                        'formname' => 'Message',
                        'formdescription' => 'Manage customer messages and note templates',
                    ]);
            }

            if (Schema::hasTable('usertypedetail')) {
                DB::table('usertypedetail')
                    ->where('formid', $messageFormId)
                    ->update([
                        'formname' => 'Message',
                        'formdescription' => 'Manage customer messages and note templates',
                    ]);
            }
        }

        DB::table('moduledetail')
            ->whereRaw('LOWER(TRIM(formname)) = ?', ['salesman message'])
            ->delete();
    }
};
