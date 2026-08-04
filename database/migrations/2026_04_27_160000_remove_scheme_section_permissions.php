<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $remove = [
        'promotion',
        'special price',
        'loyalty',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('moduleheader') || !Schema::hasTable('moduledetail')) {
            return;
        }

        foreach ($this->remove as $name) {
            $formId = DB::table('moduledetail')
                ->whereRaw('LOWER(TRIM(formname)) = ?', [$name])
                ->value('formid');

            if (!$formId) {
                continue;
            }

            if (Schema::hasTable('userdetail')) {
                DB::table('userdetail')->where('formid', $formId)->delete();
            }

            if (Schema::hasTable('usertypedetail')) {
                DB::table('usertypedetail')->where('formid', $formId)->delete();
            }

            DB::table('moduledetail')->where('formid', $formId)->delete();
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('moduleheader') || !Schema::hasTable('moduledetail')) {
            return;
        }

        $moduleId = DB::table('moduleheader')
            ->whereRaw('LOWER(TRIM(modulename)) = ?', ['scheme'])
            ->value('moduleid');

        if (!$moduleId) {
            return;
        }

        $restore = [
            ['formname' => 'Promotion',    'formdescription' => 'Manage scheme promotion groups, plans, and keys'],
            ['formname' => 'Special Price', 'formdescription' => 'Manage special price plans and pricing keys'],
            ['formname' => 'Loyalty',       'formdescription' => 'Manage loyalty groups, plans, and keys'],
        ];

        $nextOrder = ((int) DB::table('moduledetail')->where('moduleid', $moduleId)->max('order')) + 1;

        foreach ($restore as $form) {
            $nextFormId = ((int) DB::table('moduledetail')->max('formid')) + 1;
            DB::table('moduledetail')->insert([
                'formid'          => $nextFormId,
                'moduleid'        => $moduleId,
                'formname'        => $form['formname'],
                'formdescription' => $form['formdescription'],
                'order'           => $nextOrder++,
            ]);
        }
    }
};
