<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TargetGroupController extends Controller
{
    public function index(Request $request): Response
    {
        $allowedPerPage = [10, 25, 50, 100];
        $perPage = (int) $request->input('per_page', 10);
        $search = trim((string) $request->input('search', ''));

        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = 10;
        }

        $useAlternateCode = $this->useAlternateCode();

        $records = DB::table('itempackagemaster as itp')
            ->when($search !== '', function ($builder) use ($search) {
                $builder->where(function ($inner) use ($search) {
                    $inner
                        ->where('itp.packagecode', 'like', "%{$search}%")
                        ->orWhere('itp.alternatecode', 'like', "%{$search}%")
                        ->orWhere('itp.packagedescription', 'like', "%{$search}%")
                        ->orWhere('itp.arbpackagedescription', 'like', "%{$search}%");
                });
            })
            ->orderBy('itp.packagecode')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn ($record) => [
                'packagecode' => (int) $record->packagecode,
                'alternatecode' => $record->alternatecode ?? '',
                'packagedescription' => $record->packagedescription ?? '',
                'arbpackagedescription' => $record->arbpackagedescription ?? '',
                'activestatus' => (int) ($record->activestatus ?? 1),
                'createddate' => $record->cdat ? date('d-m-Y', strtotime((string) $record->cdat)) : '',
            ]);

        return Inertia::render('inventory/targetgroup/Index', [
            'records' => $records,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
            'workflowMeta' => [
                'indexUrl' => '/inventory/targetgroup',
                'baseUrl' => '/inventory/targetgroup',
                'label' => 'Target Group',
                'subtitle' => __('ui.target_group_note'),
                'useAlternateCode' => $useAlternateCode,
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('inventory/targetgroup/FormPage', [
            'mode' => 'create',
            'workflowMeta' => $this->workflowMeta(),
            'groupData' => $this->emptyRecordData(),
        ]);
    }

    public function show(int $targetgroup): Response
    {
        return Inertia::render('inventory/targetgroup/FormPage', [
            'mode' => 'view',
            'workflowMeta' => $this->workflowMeta(),
            'groupData' => $this->recordData($targetgroup),
        ]);
    }

    public function edit(int $targetgroup): Response
    {
        return Inertia::render('inventory/targetgroup/FormPage', [
            'mode' => 'edit',
            'workflowMeta' => $this->workflowMeta(),
            'groupData' => $this->recordData($targetgroup),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        $username = auth()->user()?->username ?? auth()->user()?->name ?? 'SYSTEM';

        $packageCode = ((int) DB::table('itempackagemaster')->max('packagecode')) + 1;

        DB::table('itempackagemaster')->insert([
            'packagecode' => $packageCode,
            'alternatecode' => $data['alternatecode'],
            'packagedescription' => $data['packagedescription'],
            'arbpackagedescription' => $data['arbpackagedescription'],
            'activestatus' => (int) $data['activestatus'],
            'created' => $username,
            'modified' => $username,
            'cdat' => now(),
            'mdat' => now(),
        ]);

        return redirect('/inventory/targetgroup')
            ->with('success', 'Target Group created successfully.');
    }

    public function update(Request $request, int $targetgroup): RedirectResponse
    {
        abort_unless(DB::table('itempackagemaster')->where('packagecode', $targetgroup)->exists(), 404);

        $data = $this->validatedData($request);
        $username = auth()->user()?->username ?? auth()->user()?->name ?? 'SYSTEM';

        DB::table('itempackagemaster')
            ->where('packagecode', $targetgroup)
            ->update([
                'alternatecode' => $data['alternatecode'],
                'packagedescription' => $data['packagedescription'],
                'arbpackagedescription' => $data['arbpackagedescription'],
                'activestatus' => (int) $data['activestatus'],
                'modified' => $username,
                'mdat' => now(),
            ]);

        return redirect('/inventory/targetgroup')
            ->with('success', 'Target Group updated successfully.');
    }

    public function destroy(int $targetgroup): RedirectResponse
    {
        DB::table('itempackagemaster')->where('packagecode', $targetgroup)->delete();

        return back()->with('success', 'Target Group deleted successfully.');
    }

    private function validatedData(Request $request): array
    {
        $data = $request->validate([
            'alternatecode' => ['nullable', 'string', 'max:50'],
            'packagedescription' => ['required', 'string', 'max:50'],
            'arbpackagedescription' => ['nullable', 'string', 'max:50'],
            'activestatus' => ['required', 'integer', Rule::in([0, 1])],
        ]);

        $data['alternatecode'] = $data['alternatecode'] === '' ? null : $data['alternatecode'];
        $data['arbpackagedescription'] = $data['arbpackagedescription'] === '' ? null : $data['arbpackagedescription'];

        return $data;
    }

    private function recordData(int $packageCode): array
    {
        $record = DB::table('itempackagemaster')->where('packagecode', $packageCode)->first();
        abort_unless($record, 404);

        return [
            'packagecode' => (int) $record->packagecode,
            'alternatecode' => $record->alternatecode ?? '',
            'packagedescription' => $record->packagedescription ?? '',
            'arbpackagedescription' => $record->arbpackagedescription ?? '',
            'activestatus' => (int) ($record->activestatus ?? 1),
            'createddate' => $record->cdat ? date('d-m-Y', strtotime((string) $record->cdat)) : '',
        ];
    }

    private function emptyRecordData(): array
    {
        return [
            'packagecode' => ((int) DB::table('itempackagemaster')->max('packagecode')) + 1,
            'alternatecode' => '',
            'packagedescription' => '',
            'arbpackagedescription' => '',
            'activestatus' => 1,
            'createddate' => '',
        ];
    }

    private function workflowMeta(): array
    {
        return [
            'indexUrl' => '/inventory/targetgroup',
            'baseUrl' => '/inventory/targetgroup',
            'label' => 'Target Group',
            'subtitle' => __('ui.target_group_note'),
            'useAlternateCode' => $this->useAlternateCode(),
            'permission' => 'target group',
        ];
    }

    private function useAlternateCode(): bool
    {
        if (!Schema::hasTable('controlpanel')) {
            return false;
        }

        return (int) DB::table('controlpanel')
            ->where('flagname', 'Use Alternate Code')
            ->value('status') === 1;
    }
}
