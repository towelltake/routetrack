<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use App\Support\EmailTemplateManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class EmailTemplateController extends Controller
{
    public function __construct(private readonly EmailTemplateManager $templateManager)
    {
    }

    public function index(): Response
    {
        return Inertia::render('settings/EmailTemplates', [
            'templates' => EmailTemplate::query()
                ->orderByRaw("CASE WHEN purpose = ? THEN 0 ELSE 1 END", [EmailTemplateManager::PURPOSE_PASSWORD_RESET])
                ->orderBy('name')
                ->get()
                ->map(fn (EmailTemplate $template) => [
                    'id' => $template->id,
                    'purpose' => $template->purpose,
                    'name' => $template->name,
                    'subject_en' => $template->subject_en,
                    'subject_ar' => $template->subject_ar,
                    'body_en' => $template->body_en,
                    'body_ar' => $template->body_ar,
                    'is_active' => (bool) $template->is_active,
                ])
                ->values(),
            'purposeOptions' => collect($this->templateManager->purposes())
                ->map(fn (array $definition, string $key) => [
                    'value' => $key,
                    'label' => $definition['label'],
                ])
                ->values(),
            'placeholders' => collect($this->templateManager->purposes())
                ->mapWithKeys(fn (array $definition, string $key) => [
                    $key => $definition['placeholders'],
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateTemplate($request);

        EmailTemplate::query()->create($validated);

        return back()->with('success', 'Email template created.');
    }

    public function update(Request $request, EmailTemplate $emailTemplate): RedirectResponse
    {
        $validated = $this->validateTemplate($request, $emailTemplate->id);

        $emailTemplate->update($validated);

        return back()->with('success', 'Email template updated.');
    }

    public function destroy(EmailTemplate $emailTemplate): RedirectResponse
    {
        $emailTemplate->delete();

        return back()->with('success', 'Email template deleted.');
    }

    private function validateTemplate(Request $request, ?int $ignoreId = null): array
    {
        $purposeRules = ['required', 'string', 'max:100', 'regex:/^[a-z0-9_]+$/'];
        $purposeRules[] = 'unique:email_templates,purpose' . ($ignoreId ? ',' . $ignoreId : '');

        $validated = $request->validate([
            'purpose' => $purposeRules,
            'name' => ['required', 'string', 'max:150'],
            'subject_en' => ['required', 'string', 'max:255'],
            'subject_ar' => ['required', 'string', 'max:255'],
            'body_en' => ['required', 'string'],
            'body_ar' => ['required', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        return [
            'purpose' => Str::of($validated['purpose'])->trim()->lower()->replace('-', '_')->value(),
            'name' => trim($validated['name']),
            'subject_en' => $validated['subject_en'],
            'subject_ar' => $validated['subject_ar'],
            'body_en' => $validated['body_en'],
            'body_ar' => $validated['body_ar'],
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ];
    }
}
