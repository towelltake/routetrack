<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\CustomerMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MessageController extends Controller
{
    public function index(): Response
    {
        $search = request('search');
        $perPage = (int) request('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        $messages = CustomerMessage::query()
            ->when($search, function ($query, $searchTerm) {
                $query->where(function ($inner) use ($searchTerm) {
                    $inner->where('messagekey', 'like', '%' . $searchTerm . '%')
                        ->orWhere('alternatecode', 'like', '%' . $searchTerm . '%')
                        ->orWhere('messagedescription', 'like', '%' . $searchTerm . '%')
                        ->orWhere('messageline1', 'like', '%' . $searchTerm . '%')
                        ->orWhere('messageline2', 'like', '%' . $searchTerm . '%')
                        ->orWhere('messageline3', 'like', '%' . $searchTerm . '%')
                        ->orWhere('messageline4', 'like', '%' . $searchTerm . '%');
                });
            })
            ->orderBy('messagekey')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('account/message/Index', [
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
            'messages' => $messages,
        ]);
    }

    public function create(): Response
    {
        $props = $this->formProps();
        $props['messageData']['messagekey'] = $this->nextMessageKey();

        return Inertia::render('account/message/Create', $props);
    }

    public function show(CustomerMessage $message): Response
    {
        return Inertia::render('account/message/View', $this->formProps($message));
    }

    public function edit(CustomerMessage $message): Response
    {
        return Inertia::render('account/message/Edit', $this->formProps($message));
    }

    public function store(Request $request): RedirectResponse
    {
        $payload = $this->validatedData($request);
        $payload['created'] = auth()->user()?->username ?? auth()->user()?->name;
        $payload['cdat'] = now();
        $payload['modified'] = auth()->user()?->username ?? auth()->user()?->name;
        $payload['mdat'] = now();

        CustomerMessage::create($payload);

        return redirect()
            ->route('account.customer-message.index')
            ->with('success', 'Customer message created.');
    }

    public function update(Request $request, CustomerMessage $message): RedirectResponse
    {
        $payload = $this->validatedData($request, $message);
        $payload['modified'] = auth()->user()?->username ?? auth()->user()?->name;
        $payload['mdat'] = now();

        $message->update($payload);

        return redirect()
            ->route('account.customer-message.index')
            ->with('success', 'Customer message updated.');
    }

    public function destroy(CustomerMessage $message): RedirectResponse
    {
        try {
            $message->delete();
        } catch (\Throwable $e) {
            return back()->with('error', 'Cannot delete: record is in use.');
        }

        return back()->with('success', 'Customer message deleted.');
    }

    private function formProps(?CustomerMessage $message = null): array
    {
        return [
            'messageData' => $this->messageFormData($message),
            'optionSets' => [
                'statusOptions' => [
                    ['id' => 1, 'label' => 'Active'],
                    ['id' => 0, 'label' => 'Inactive'],
                ],
            ],
        ];
    }

    private function messageFormData(?CustomerMessage $message): array
    {
        $record = $message?->toArray() ?? [];

        return array_merge($this->defaultMessageData(), array_intersect_key(
            $record,
            array_flip(array_keys($this->defaultMessageData()))
        ));
    }

    private function defaultMessageData(): array
    {
        return [
            'messagekey' => null,
            'alternatecode' => '',
            'messagedescription' => '',
            'messageline1' => '',
            'messageline2' => '',
            'messageline3' => '',
            'messageline4' => '',
            'arbmessageline1' => '',
            'arbmessageline2' => '',
            'arbmessageline3' => '',
            'arbmessageline4' => '',
            'activestatus' => 1,
            'created' => null,
            'cdat' => null,
            'modified' => null,
            'mdat' => null,
        ];
    }

    private function validatedData(Request $request, ?CustomerMessage $message = null): array
    {
        $data = $request->validate([
            'alternatecode' => ['nullable', 'string', 'max:50'],
            'messagedescription' => ['required', 'string', 'max:50'],
            'messageline1' => ['nullable', 'string', 'max:40'],
            'messageline2' => ['nullable', 'string', 'max:40'],
            'messageline3' => ['nullable', 'string', 'max:40'],
            'messageline4' => ['nullable', 'string', 'max:40'],
            'arbmessageline1' => ['nullable', 'string', 'max:100'],
            'arbmessageline2' => ['nullable', 'string', 'max:100'],
            'arbmessageline3' => ['nullable', 'string', 'max:100'],
            'arbmessageline4' => ['nullable', 'string', 'max:100'],
            'activestatus' => ['required', 'integer', 'in:0,1'],
        ]);

        foreach (array_keys($this->defaultMessageData()) as $key) {
            if (array_key_exists($key, $data) && $data[$key] === '') {
                $data[$key] = null;
            }
        }

        return $data;
    }

    private function nextMessageKey(): int
    {
        return ((int) CustomerMessage::max('messagekey')) + 1;
    }
}
