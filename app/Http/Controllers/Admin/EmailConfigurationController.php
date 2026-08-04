<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailConfiguration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmailConfigurationController extends Controller
{
    public function index(): Response
    {
        $config = EmailConfiguration::query()->first();

        return Inertia::render('settings/EmailConfiguration', [
            'form' => [
                'id' => $config?->id,
                'mailer' => $config?->mailer ?? 'smtp',
                'host' => $config?->host ?? '',
                'port' => $config?->port ?? 587,
                'username' => $config?->username ?? '',
                'password' => $config?->password ?? '',
                'encryption' => $config?->encryption ?? 'tls',
                'from_address' => $config?->from_address ?? '',
                'from_name' => $config?->from_name ?? config('app.name', 'TRAC'),
                'is_active' => (bool) ($config?->is_active ?? true),
            ],
            'mailerOptions' => [
                ['value' => 'smtp', 'label' => 'SMTP'],
                ['value' => 'log', 'label' => 'Log'],
                ['value' => 'sendmail', 'label' => 'Sendmail'],
            ],
            'encryptionOptions' => [
                ['value' => 'tls', 'label' => 'TLS'],
                ['value' => 'ssl', 'label' => 'SSL'],
                ['value' => '', 'label' => 'None'],
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'mailer' => ['required', 'string', 'in:smtp,log,sendmail'],
            'host' => ['nullable', 'string', 'max:255'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'encryption' => ['nullable', 'string', 'in:tls,ssl,'],
            'from_address' => ['nullable', 'email', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $config = EmailConfiguration::query()->firstOrNew();
        $config->fill([
            'mailer' => $validated['mailer'],
            'host' => $validated['host'] ?? null,
            'port' => $validated['port'] ?? null,
            'username' => $validated['username'] ?? null,
            'password' => $validated['password'] ?? null,
            'encryption' => $validated['encryption'] ?? null,
            'from_address' => $validated['from_address'] ?? null,
            'from_name' => $validated['from_name'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ]);
        $config->save();

        return back()->with('success', 'Email configuration updated.');
    }
}
