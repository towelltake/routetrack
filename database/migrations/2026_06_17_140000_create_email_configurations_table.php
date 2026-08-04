<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('email_configurations')) {
            Schema::create('email_configurations', function (Blueprint $table) {
                $table->id();
                $table->string('mailer', 50)->default('smtp');
                $table->string('host')->nullable();
                $table->unsignedInteger('port')->nullable();
                $table->string('username')->nullable();
                $table->string('password')->nullable();
                $table->string('encryption', 20)->nullable();
                $table->string('from_address')->nullable();
                $table->string('from_name')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        DB::table('email_configurations')->updateOrInsert(
            ['id' => 1],
            [
                'mailer' => 'smtp',
                'host' => null,
                'port' => 587,
                'username' => null,
                'password' => null,
                'encryption' => 'tls',
                'from_address' => null,
                'from_name' => config('app.name', 'TRAC'),
                'is_active' => 1,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        $this->addPermission();
    }

    public function down(): void
    {
        if (Schema::hasTable('moduledetail')) {
            $formId = DB::table('moduledetail')
                ->whereRaw('LOWER(TRIM(formname)) = ?', ['email configuration'])
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
        }

        Schema::dropIfExists('email_configurations');
    }

    private function addPermission(): void
    {
        if (!Schema::hasTable('moduleheader') || !Schema::hasTable('moduledetail')) {
            return;
        }

        $moduleId = (int) DB::table('moduleheader')
            ->whereRaw('LOWER(TRIM(modulename)) = ?', ['settings'])
            ->value('moduleid');

        if ($moduleId <= 0) {
            return;
        }

        $existing = DB::table('moduledetail')
            ->whereRaw('LOWER(TRIM(formname)) = ?', ['email configuration'])
            ->first();

        $source = DB::table('moduledetail')
            ->whereRaw('LOWER(TRIM(formname)) = ?', ['email templates'])
            ->first()
            ?: DB::table('moduledetail')
                ->whereRaw('LOWER(TRIM(formname)) = ?', ['control panel'])
                ->first();

        $description = 'Email Configuration';

        if ($existing) {
            DB::table('moduledetail')
                ->where('formid', $existing->formid)
                ->update([
                    'moduleid' => $moduleId,
                    'formname' => 'Email Configuration',
                    'formdescription' => $description,
                ]);

            if ($source) {
                $this->syncPermissionRows('userdetail', 'userid', (int) $source->formid, (int) $existing->formid, $moduleId, 'Email Configuration', $description);
                $this->syncPermissionRows('usertypedetail', 'usertypeid', (int) $source->formid, (int) $existing->formid, $moduleId, 'Email Configuration', $description);
            }

            return;
        }

        $nextFormId = ((int) DB::table('moduledetail')->max('formid')) + 1;
        $nextOrder = ((int) DB::table('moduledetail')->where('moduleid', $moduleId)->max('order')) + 1;
        if ($nextOrder <= 0) {
            $nextOrder = 1;
        }

        if ($source) {
            $payload = (array) $source;
            unset($payload['formid']);
            $payload['formid'] = $nextFormId;
            $payload['moduleid'] = $moduleId;
            $payload['formname'] = 'Email Configuration';
            $payload['formdescription'] = $description;
            $payload['order'] = $nextOrder;

            DB::table('moduledetail')->insert($payload);

            $this->syncPermissionRows('userdetail', 'userid', (int) $source->formid, $nextFormId, $moduleId, 'Email Configuration', $description);
            $this->syncPermissionRows('usertypedetail', 'usertypeid', (int) $source->formid, $nextFormId, $moduleId, 'Email Configuration', $description);
            return;
        }

        DB::table('moduledetail')->insert([
            'formid' => $nextFormId,
            'moduleid' => $moduleId,
            'formname' => 'Email Configuration',
            'formdescription' => $description,
            'order' => $nextOrder,
        ]);
    }

    private function syncPermissionRows(
        string $table,
        string $ownerColumn,
        int $sourceFormId,
        int $targetFormId,
        int $moduleId,
        string $formName,
        string $description
    ): void {
        if (!Schema::hasTable($table)) {
            return;
        }

        $sourceRows = DB::table($table)->where('formid', $sourceFormId)->get();

        foreach ($sourceRows as $row) {
            $payload = (array) $row;
            $ownerId = (int) ($row->{$ownerColumn} ?? 0);
            $payload['formid'] = $targetFormId;
            $payload['moduleid'] = $moduleId;
            $payload['formname'] = $formName;
            $payload['formdescription'] = $description;

            $exists = DB::table($table)
                ->where($ownerColumn, $ownerId)
                ->where('formid', $targetFormId)
                ->exists();

            if ($exists) {
                DB::table($table)
                    ->where($ownerColumn, $ownerId)
                    ->where('formid', $targetFormId)
                    ->update($payload);
                continue;
            }

            DB::table($table)->insert($payload);
        }
    }
};
