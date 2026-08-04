<?php

namespace App\Support;

use App\Models\EmailTemplate;

class EmailTemplateManager
{
    public const PURPOSE_PASSWORD_RESET = 'password_reset';
    public const PURPOSE_EMAIL_VERIFICATION = 'email_verification';

    public function purposes(): array
    {
        return [
            self::PURPOSE_PASSWORD_RESET => [
                'label' => 'Password Reset',
                'placeholders' => [
                    'app_name',
                    'user_name',
                    'user_email',
                    'reset_url',
                    'reset_link',
                    'expiry_minutes',
                    'current_year',
                ],
            ],
            self::PURPOSE_EMAIL_VERIFICATION => [
                'label' => 'Email Verification',
                'placeholders' => [
                    'app_name',
                    'user_name',
                    'user_email',
                    'verification_url',
                    'verification_link',
                    'current_year',
                ],
            ],
        ];
    }

    public function render(string $purpose, ?string $locale, array $placeholders = []): array
    {
        $template = EmailTemplate::query()
            ->where('purpose', $purpose)
            ->where('is_active', true)
            ->first();

        $locale = $locale === 'ar' ? 'ar' : 'en';
        $defaults = $this->defaultTemplate($purpose, $locale);

        $subject = trim((string) ($template?->{"subject_{$locale}"} ?? ''));
        $body = trim((string) ($template?->{"body_{$locale}"} ?? ''));

        if ($subject === '') {
            $subject = $defaults['subject'];
        }

        if ($body === '') {
            $body = $defaults['body'];
        }

        return [
            'subject' => $this->replacePlaceholders($subject, $placeholders),
            'body' => $this->replacePlaceholders($body, $placeholders),
        ];
    }

    public function defaultTemplate(string $purpose, string $locale = 'en'): array
    {
        $locale = $locale === 'ar' ? 'ar' : 'en';

        return match ($purpose) {
            self::PURPOSE_PASSWORD_RESET => $locale === 'ar'
                ? [
                    'subject' => 'إعادة تعيين كلمة المرور لحساب {{ app_name }}',
                    'body' => '<p>مرحبًا {{ user_name }}،</p><p>تلقينا طلبًا لإعادة تعيين كلمة المرور لحسابك في {{ app_name }}.</p><p><a href="{{ reset_url }}">اضغط هنا لإعادة تعيين كلمة المرور</a></p><p>تنتهي صلاحية هذا الرابط خلال {{ expiry_minutes }} دقيقة.</p><p>إذا لم تطلب إعادة تعيين كلمة المرور، يمكنك تجاهل هذه الرسالة.</p>',
                ]
                : [
                    'subject' => 'Reset your {{ app_name }} password',
                    'body' => '<p>Hello {{ user_name }},</p><p>We received a request to reset the password for your {{ app_name }} account.</p><p><a href="{{ reset_url }}">Click here to reset your password</a></p><p>This link expires in {{ expiry_minutes }} minutes.</p><p>If you did not request a password reset, you can ignore this email.</p>',
                ],
            self::PURPOSE_EMAIL_VERIFICATION => $locale === 'ar'
                ? [
                    'subject' => 'تحقق من بريدك الإلكتروني في {{ app_name }}',
                    'body' => '<p>مرحبًا {{ user_name }}،</p><p>يرجى تأكيد عنوان بريدك الإلكتروني لمتابعة استخدام {{ app_name }}.</p><p><a href="{{ verification_url }}">اضغط هنا للتحقق من البريد الإلكتروني</a></p>',
                ]
                : [
                    'subject' => 'Verify your {{ app_name }} email address',
                    'body' => '<p>Hello {{ user_name }},</p><p>Please confirm your email address to continue using {{ app_name }}.</p><p><a href="{{ verification_url }}">Click here to verify your email</a></p>',
                ],
            default => [
                'subject' => '{{ app_name }} notification',
                'body' => '<p>Hello {{ user_name }},</p><p>This is a notification from {{ app_name }}.</p>',
            ],
        };
    }

    private function replacePlaceholders(string $value, array $placeholders): string
    {
        $replacements = [];

        foreach ($placeholders as $key => $content) {
            $replacements['{{ ' . $key . ' }}'] = (string) $content;
            $replacements['{{' . $key . '}}'] = (string) $content;
        }

        return strtr($value, $replacements);
    }
}
