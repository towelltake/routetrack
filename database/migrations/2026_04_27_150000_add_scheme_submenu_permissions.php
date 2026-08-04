<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $forms = [
        ['formname' => 'Qualification Group', 'formdescription' => 'Manage promotion qualification groups'],
        ['formname' => 'Assignment Group',    'formdescription' => 'Manage promotion assignment groups'],
        ['formname' => 'Promo Plan',          'formdescription' => 'Manage promotion plans'],
        ['formname' => 'Promo Key',           'formdescription' => 'Manage promotion keys'],
        ['formname' => 'Pricing Plan',        'formdescription' => 'Manage special price plans'],
        ['formname' => 'Pricing Key',         'formdescription' => 'Manage special price keys'],
        ['formname' => 'Loyalty Group',       'formdescription' => 'Manage loyalty qualification groups'],
        ['formname' => 'Loyalty Plan',        'formdescription' => 'Manage loyalty plans'],
        ['formname' => 'Loyalty Key',         'formdescription' => 'Manage loyalty keys'],
    ];

    public function up(): void
    {
        if (!Schema::hasTable('moduleheader') || !Schema::hasTable('moduledetail')) {
            return;
        }

        $moduleId = DB::table('moduleheader')
            ->whereRaw('LOWER(TRIM(modulename)) = ?', ['scheme'])
            ->value('moduleid');

        if (!$moduleId) {
            $moduleId = ((int) DB::table('moduleheader')->max('moduleid')) + 1;
            DB::table('moduleheader')->insert(['moduleid' => $moduleId, 'modulename' => 'Scheme']);
        }

        // Use the Promotion row as the permission-flag template
        $source = DB::table('moduledetail')
            ->whereRaw('LOWER(TRIM(formname)) = ?', ['promotion'])
            ->first();

        $nextOrder = ((int) DB::table('moduledetail')->where('moduleid', $moduleId)->max('order')) + 1;

        foreach ($this->forms as $form) {
            $normalised = strtolower(trim($form['formname']));

            $existing = DB::table('moduledetail')
                ->whereRaw('LOWER(TRIM(formname)) = ?', [$normalised])
                ->first();

            if ($existing) {
                DB::table('moduledetail')->where('formid', $existing->formid)->update([
                    'moduleid'        => $moduleId,
                    'formname'        => $form['formname'],
                    'formdescription' => $form['formdescription'],
                ]);
                $nextOrder++;
                continue;
            }

            $nextFormId = ((int) DB::table('moduledetail')->max('formid')) + 1;

            if ($source) {
                $payload                    = (array) $source;
                $payload['formid']          = $nextFormId;
                $payload['moduleid']        = $moduleId;
                $payload['formname']        = $form['formname'];
                $payload['formdescription'] = $form['formdescription'];
                $payload['order']           = $nextOrder;
                DB::table('moduledetail')->insert($payload);

                if (Schema::hasTable('usertypedetail')) {
                    $rows = DB::table('usertypedetail')
                        ->where('formid', $source->formid)
                        ->get()
                        ->map(fn ($r) => array_merge((array) $r, [
                            'formid'          => $nextFormId,
                            'moduleid'        => $moduleId,
                            'formname'        => $form['formname'],
                            'formdescription' => $form['formdescription'],
                        ]))->all();

                    if ($rows) {
                        DB::table('usertypedetail')->insert($rows);
                    }
                }

                if (Schema::hasTable('userdetail')) {
                    $rows = DB::table('userdetail')
                        ->where('formid', $source->formid)
                        ->get()
                        ->map(fn ($r) => array_merge((array) $r, [
                            'formid'          => $nextFormId,
                            'moduleid'        => $moduleId,
                            'formname'        => $form['formname'],
                            'formdescription' => $form['formdescription'],
                        ]))->all();

                    if ($rows) {
                        DB::table('userdetail')->insert($rows);
                    }
                }
            } else {
                DB::table('moduledetail')->insert([
                    'formid'          => $nextFormId,
                    'moduleid'        => $moduleId,
                    'formname'        => $form['formname'],
                    'formdescription' => $form['formdescription'],
                    'order'           => $nextOrder,
                ]);
            }

            $nextOrder++;
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('moduleheader') || !Schema::hasTable('moduledetail')) {
            return;
        }

        foreach ($this->forms as $form) {
            $formId = DB::table('moduledetail')
                ->whereRaw('LOWER(TRIM(formname)) = ?', [strtolower(trim($form['formname']))])
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
};
