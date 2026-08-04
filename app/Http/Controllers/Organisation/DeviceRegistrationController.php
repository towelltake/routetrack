<?php

namespace App\Http\Controllers\Organisation;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DeviceRegistrationController extends Controller
{
    private ?string $deviceTable = null;
    private ?string $tableMode = null;

    public function index(): Response
    {
        $search = (string) request('search', '');
        $perPage = (int) request('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        $devices = $this->deviceTableQuery()
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where($this->deviceIdColumn(), 'like', '%' . $search . '%')
                        ->orWhere('remarks', 'like', '%' . $search . '%');
                });
            })
            ->orderBy($this->primaryKeyColumn())
            ->paginate($perPage, $this->selectColumns())
            ->withQueryString();

        return Inertia::render('organisation/device-registration/Index', [
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
            'devices' => $devices,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'device_id' => ['required', 'string', 'max:50'],
            'remarks' => ['required', 'string', 'max:50'],
        ]);

        $table = $this->deviceTableQuery();
        $deviceId = trim($data['device_id']);
        $remarks = trim($data['remarks']);

        if ((int) $table->count() >= 150) {
            return back()->withErrors([
                'device_id' => 'Registration exceeds the maximum number of purchased licenses.',
            ]);
        }

        if ($this->deviceTableQuery()->where($this->deviceIdColumn(), $deviceId)->exists()) {
            return back()->withErrors([
                'device_id' => 'Device already registered.',
            ])->withInput();
        }

        $payload = [
            $this->deviceIdColumn() => $deviceId,
            'remarks' => $remarks,
        ];

        if ($this->usesLegacyTable()) {
            $payload['company_id'] = 1;
        } else {
            $payload['companyid'] = null;
            $payload['statusflag'] = 1;
        }

        $this->deviceTableQuery()->insert($payload);

        return back()->with('success', 'Device registered.');
    }

    public function destroy(int $deviceRegistration): RedirectResponse
    {
        $this->deviceTableQuery()
            ->where($this->primaryKeyColumn(), $deviceRegistration)
            ->delete();

        return back()->with('success', 'Device deleted.');
    }

    private function deviceTableQuery(): Builder
    {
        return DB::query()->from(DB::raw($this->deviceTable()));
    }

    private function deviceTable(): string
    {
        if ($this->deviceTable !== null) {
            return $this->deviceTable;
        }

        foreach ($this->tableCandidates() as [$mode, $table]) {
            if ($this->tableExists($table)) {
                $this->tableMode = $mode;

                return $this->deviceTable = $table;
            }
        }

        $this->tableMode = 'legacy';

        return $this->deviceTable = 'tbl_device';
    }

    private function tableExists(string $table): bool
    {
        if ($table === '') {
            return false;
        }

        $database = DB::getDatabaseName();
        $result = DB::selectOne(
            'SELECT EXISTS(
                SELECT 1
                FROM information_schema.tables
                WHERE table_schema = ?
                  AND table_name = ?
            ) AS table_exists',
            [$database, $table]
        );

        return (bool) ($result->table_exists ?? false);
    }

    private function tableCandidates(): array
    {
        $prefix = (string) config('database.connections.mysql.prefix', '');

        return [
            ['legacy', $prefix . 'tbl_device'],
            ['legacy', 'tbl_device'],
            ['modern', $prefix . 'devicemaster'],
            ['modern', 'devicemaster'],
        ];
    }

    private function usesLegacyTable(): bool
    {
        $this->deviceTable();

        return $this->tableMode === 'legacy';
    }

    private function primaryKeyColumn(): string
    {
        return $this->usesLegacyTable() ? 'primary_key' : 'id';
    }

    private function deviceIdColumn(): string
    {
        return $this->usesLegacyTable() ? 'device_id' : 'deviceid';
    }

    private function selectColumns(): array
    {
        if ($this->usesLegacyTable()) {
            return ['primary_key', 'device_id', 'remarks'];
        }

        return [
            DB::raw('id as primary_key'),
            DB::raw('deviceid as device_id'),
            'remarks',
        ];
    }
}
