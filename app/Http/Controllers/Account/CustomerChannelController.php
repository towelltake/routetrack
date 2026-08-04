<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\ChannelMaster;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CustomerChannelController extends Controller
{
    public function index(): Response
    {
        $search = request('search');
        $perPage = (int) request('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        $channels = ChannelMaster::query()
            ->when($search, function ($query, $searchTerm) {
                $query->where(function ($inner) use ($searchTerm) {
                    $inner->where('channelcode', 'like', '%' . $searchTerm . '%')
                        ->orWhere('alternatecode', 'like', '%' . $searchTerm . '%')
                        ->orWhere('channelname', 'like', '%' . $searchTerm . '%')
                        ->orWhere('arbchannelname', 'like', '%' . $searchTerm . '%');
                });
            })
            ->orderBy('channelcode')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('account/customerchannel/Index', [
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
            'channels' => $channels,
            'nextCode' => $this->nextChannelCode(),
        ]);
    }

    public function create(): Response
    {
        $props = $this->formProps();
        $props['channelData']['channelcode'] = $this->nextChannelCode();

        return Inertia::render('account/customerchannel/Create', $props);
    }

    public function show(ChannelMaster $customerChannel): Response
    {
        return Inertia::render('account/customerchannel/View', $this->formProps($customerChannel));
    }

    public function edit(ChannelMaster $customerChannel): Response
    {
        return Inertia::render('account/customerchannel/Edit', $this->formProps($customerChannel));
    }

    public function store(Request $request): RedirectResponse
    {
        $payload = $this->validatedData($request);
        $username = auth()->user()?->username ?? auth()->user()?->name;
        $payload['created'] = $username;
        $payload['cdat'] = now();
        $payload['modified'] = $username;
        $payload['mdat'] = now();

        ChannelMaster::create($payload);

        return redirect()
            ->route('account.customer-channel.index')
            ->with('success', 'Customer channel created.');
    }

    public function update(Request $request, ChannelMaster $customerChannel): RedirectResponse
    {
        $payload = $this->validatedData($request, $customerChannel);
        $payload['modified'] = auth()->user()?->username ?? auth()->user()?->name;
        $payload['mdat'] = now();

        $customerChannel->update($payload);

        return redirect()
            ->route('account.customer-channel.index')
            ->with('success', 'Customer channel updated.');
    }

    public function destroy(ChannelMaster $customerChannel): RedirectResponse
    {
        try {
            $customerChannel->delete();
        } catch (\Throwable $e) {
            return back()->with('error', 'Cannot delete: record is in use.');
        }

        return back()->with('success', 'Customer channel deleted.');
    }

    private function formProps(?ChannelMaster $channel = null): array
    {
        return [
            'channelData' => $this->channelFormData($channel),
            'optionSets' => [
                'statusOptions' => [
                    ['id' => 1, 'label' => 'Active'],
                    ['id' => 0, 'label' => 'Inactive'],
                ],
            ],
        ];
    }

    private function channelFormData(?ChannelMaster $channel): array
    {
        $record = $channel?->toArray() ?? [];

        return array_merge($this->defaultChannelData(), array_intersect_key(
            $record,
            array_flip(array_keys($this->defaultChannelData()))
        ));
    }

    private function defaultChannelData(): array
    {
        return [
            'channelcode' => null,
            'alternatecode' => '',
            'channelname' => '',
            'arbchannelname' => '',
            'activestatus' => 1,
            'created' => null,
            'cdat' => null,
            'modified' => null,
            'mdat' => null,
        ];
    }

    private function validatedData(Request $request, ?ChannelMaster $channel = null): array
    {
        $data = $request->validate([
            'alternatecode' => ['nullable', 'string', 'max:50'],
            'channelname' => ['required', 'string', 'max:50'],
            'arbchannelname' => ['nullable', 'string', 'max:50'],
            'activestatus' => ['required', 'integer', Rule::in([0, 1])],
        ]);

        foreach (['alternatecode', 'arbchannelname'] as $key) {
            if (array_key_exists($key, $data) && $data[$key] === '') {
                $data[$key] = null;
            }
        }

        return $data;
    }

    private function nextChannelCode(): int
    {
        return ((int) ChannelMaster::max('channelcode')) + 1;
    }
}
