<?php

namespace App\Notifications;

use App\Support\EmailTemplateManager;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Config;

class TemplatePasswordResetNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly string $token)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $resetUrl = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        $broker = Config::get('auth.defaults.passwords');
        $expiryMinutes = (int) Config::get("auth.passwords.{$broker}.expire", 60);

        $template = app(EmailTemplateManager::class)->render(
            EmailTemplateManager::PURPOSE_PASSWORD_RESET,
            app()->getLocale(),
            [
                'app_name' => config('app.name', 'TRAC'),
                'user_name' => $notifiable->name ?: $notifiable->username ?: 'User',
                'user_email' => $notifiable->getEmailForPasswordReset(),
                'reset_url' => $resetUrl,
                'reset_link' => $resetUrl,
                'expiry_minutes' => $expiryMinutes,
                'current_year' => now()->year,
            ],
        );

        return (new MailMessage)
            ->subject($template['subject'])
            ->view('emails.template', [
                'content' => $template['body'],
                'locale' => app()->getLocale(),
                'subject' => $template['subject'],
            ]);
    }
}
