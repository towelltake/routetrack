<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\SalesmanMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SalesmanMessageController extends Controller
{
    public function index(): Response
    {
        $search = request('search');
        $perPage = (int) request('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        $messages = SalesmanMessage::query()
            ->when($search, function ($query, $searchTerm) {
                $query->where(function ($inner) use ($searchTerm) {
                    $inner->where('messagekey', 'like', '%' . $searchTerm . '%')
                        ->orWhere('alternatecode', 'like', '%' . $searchTerm . '%')
                        ->orWhere('messagedescription', 'like', '%' . $searchTerm . '%')
                        ->orWhere('message1', 'like', '%' . $searchTerm . '%')
                        ->orWhere('message2', 'like', '%' . $searchTerm . '%')
                        ->orWhere('message3', 'like', '%' . $searchTerm . '%')
                        ->orWhere('message4', 'like', '%' . $searchTerm . '%');
                });
            })
            ->orderBy('messagekey')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('account/salesmanmessage/Index', [
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

        return Inertia::render('account/salesmanmessage/Create', $props);
    }

    public function show(SalesmanMessage $salesmanMessage): Response
    {
        return Inertia::render('account/salesmanmessage/View', $this->formProps($salesmanMessage));
    }

    public function edit(SalesmanMessage $salesmanMessage): Response
    {
        return Inertia::render('account/salesmanmessage/Edit', $this->formProps($salesmanMessage));
    }

    public function store(Request $request): RedirectResponse
    {
        $payload = $this->validatedData($request);
        $payload['created'] = auth()->user()?->username ?? auth()->user()?->name;
        $payload['cdat'] = now();
        $payload['modified'] = auth()->user()?->username ?? auth()->user()?->name;
        $payload['mdat'] = now();

        SalesmanMessage::create($payload);

        return redirect()
            ->route('account.salesman-message.index')
            ->with('success', 'Salesman message created.');
    }

    public function update(Request $request, SalesmanMessage $salesmanMessage): RedirectResponse
    {
        $payload = $this->validatedData($request);
        $payload['modified'] = auth()->user()?->username ?? auth()->user()?->name;
        $payload['mdat'] = now();

        $salesmanMessage->update($payload);

        return redirect()
            ->route('account.salesman-message.index')
            ->with('success', 'Salesman message updated.');
    }

    public function destroy(SalesmanMessage $salesmanMessage): RedirectResponse
    {
        try {
            $salesmanMessage->delete();
        } catch (\Throwable $e) {
            return back()->with('error', 'Cannot delete: record is in use.');
        }

        return back()->with('success', 'Salesman message deleted.');
    }

    private function formProps(?SalesmanMessage $message = null): array
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

    private function messageFormData(?SalesmanMessage $message): array
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
            'message1' => '',
            'message2' => '',
            'message3' => '',
            'message4' => '',
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

    private function validatedData(Request $request): array
    {
        $data = $request->validate([
            'alternatecode' => ['nullable', 'string', 'max:50'],
            'messagedescription' => ['required', 'string', 'max:50'],
            'message1' => ['nullable', 'string', 'max:40'],
            'message2' => ['nullable', 'string', 'max:40'],
            'message3' => ['nullable', 'string', 'max:40'],
            'message4' => ['nullable', 'string', 'max:40'],
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
        return ((int) SalesmanMessage::max('messagekey')) + 1;
    }
}
