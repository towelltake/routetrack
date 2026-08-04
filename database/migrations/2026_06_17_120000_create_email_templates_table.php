<?php

use App\Support\EmailTemplateManager;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('email_templates')) {
            Schema::create('email_templates', function (Blueprint $table) {
                $table->id();
                $table->string('purpose', 100)->unique();
                $table->string('name', 150);
                $table->string('subject_en', 255);
                $table->string('subject_ar', 255);
                $table->longText('body_en');
                $table->longText('body_ar');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        $templates = [
            EmailTemplateManager::PURPOSE_PASSWORD_RESET => [
                'name' => 'Password Reset',
                'subject_en' => 'Reset your {{ app_name }} password',
                'subject_ar' => 'إعادة تعيين كلمة المرور لحساب {{ app_name }}',
                'body_en' => '<p>Hello {{ user_name }},</p><p>We received a request to reset the password for your {{ app_name }} account.</p><p><a href="{{ reset_url }}">Click here to reset your password</a></p><p>This link expires in {{ expiry_minutes }} minutes.</p><p>If you did not request a password reset, you can ignore this email.</p>',
                'body_ar' => '<p>مرحبًا {{ user_name }}،</p><p>تلقينا طلبًا لإعادة تعيين كلمة المرور لحسابك في {{ app_name }}.</p><p><a href="{{ reset_url }}">اضغط هنا لإعادة تعيين كلمة المرور</a></p><p>تنتهي صلاحية هذا الرابط خلال {{ expiry_minutes }} دقيقة.</p><p>إذا لم تطلب إعادة تعيين كلمة المرور، يمكنك تجاهل هذه الرسالة.</p>',
            ],
            EmailTemplateManager::PURPOSE_EMAIL_VERIFICATION => [
                'name' => 'Email Verification',
                'subject_en' => 'Verify your {{ app_name }} email address',
                'subject_ar' => 'تحقق من بريدك الإلكتروني في {{ app_name }}',
                'body_en' => '<p>Hello {{ user_name }},</p><p>Please confirm your email address to continue using {{ app_name }}.</p><p><a href="{{ verification_url }}">Click here to verify your email</a></p>',
                'body_ar' => '<p>مرحبًا {{ user_name }}،</p><p>يرجى تأكيد عنوان بريدك الإلكتروني لمتابعة استخدام {{ app_name }}.</p><p><a href="{{ verification_url }}">اضغط هنا للتحقق من البريد الإلكتروني</a></p>',
            ],
        ];

        foreach ($templates as $purpose => $template) {
            DB::table('email_templates')->updateOrInsert(
                ['purpose' => $purpose],
                [
                    'name' => $template['name'],
                    'subject_en' => $template['subject_en'],
                    'subject_ar' => $template['subject_ar'],
                    'body_en' => $template['body_en'],
                    'body_ar' => $template['body_ar'],
                    'is_active' => 1,
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        }

        $this->addPermission();
    }

    public function down(): void
    {
        if (Schema::hasTable('moduledetail')) {
            $formId = DB::table('moduledetail')
                ->whereRaw('LOWER(TRIM(formname)) = ?', ['email templates'])
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

        Schema::dropIfExists('email_templates');
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
            ->whereRaw('LOWER(TRIM(formname)) = ?', ['email templates'])
            ->first();

        $source = DB::table('moduledetail')
            ->whereRaw('LOWER(TRIM(formname)) = ?', ['control panel'])
            ->first()
            ?: DB::table('moduledetail')
                ->whereRaw('LOWER(TRIM(formname)) = ?', ['basic setup'])
                ->first();

        $description = 'Email Templates';

        if ($existing) {
            DB::table('moduledetail')
                ->where('formid', $existing->formid)
                ->update([
                    'moduleid' => $moduleId,
                    'formname' => 'Email Templates',
                    'formdescription' => $description,
                ]);

            if ($source) {
                $this->syncPermissionRows('userdetail', 'userid', (int) $source->formid, (int) $existing->formid, $moduleId, 'Email Templates', $description);
                $this->syncPermissionRows('usertypedetail', 'usertypeid', (int) $source->formid, (int) $existing->formid, $moduleId, 'Email Templates', $description);
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
            $payload['formname'] = 'Email Templates';
            $payload['formdescription'] = $description;
            $payload['order'] = $nextOrder;

            DB::table('moduledetail')->insert($payload);

            $this->syncPermissionRows('userdetail', 'userid', (int) $source->formid, $nextFormId, $moduleId, 'Email Templates', $description);
            $this->syncPermissionRows('usertypedetail', 'usertypeid', (int) $source->formid, $nextFormId, $moduleId, 'Email Templates', $description);
            return;
        }

        DB::table('moduledetail')->insert([
            'formid' => $nextFormId,
            'moduleid' => $moduleId,
            'formname' => 'Email Templates',
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

        $sourceRows = DB::table($table)
            ->where('formid', $sourceFormId)
            ->get();

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
