<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Services\AccessScopeService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class CustomerInventoryController extends Controller
{
    public function index(Request $request): Response
    {
        $scope = app(AccessScopeService::class);
        $user = $request->user();
        $cocAlias = 'coc';
        $routeAlias = 'route';
        $customerAlias = 'customer';
        $qualifiedCocAlias = DB::getTablePrefix() . $cocAlias;
        $qualifiedRouteAlias = DB::getTablePrefix() . $routeAlias;
        $qualifiedCustomerAlias = DB::getTablePrefix() . $customerAlias;
        $allowedPerPage = [10, 25, 50, 100];
        $allowedSorts = ['routecode', 'routename', 'salesmancode', 'visitdate', 'customercode', 'alternatecode', 'customername'];
        $perPage = (int) $request->input('per_page', 10);
        $sortBy = (string) $request->input('sort_by', 'visitdate');
        $sortDir = $request->input('sort_dir', 'desc') === 'asc' ? 'asc' : 'desc';
        $selectedDate = $this->selectedDate($request)->toDateString();
        $search = trim((string) $request->input('search', ''));

        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = 10;
        }

        if (!in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'visitdate';
        }

        if (!$this->hasOverviewTables()) {
            return Inertia::render('transaction/customer-inventory/Index', [
                'documents' => $this->emptyPaginator($request, $perPage),
                'filters' => [
                    'date' => $selectedDate,
                    'search' => $search,
                    'per_page' => $perPage,
                    'sort_by' => $sortBy,
                    'sort_dir' => $sortDir,
                ],
            ]);
        }

        $query = DB::table("customeroperationscontrol as {$cocAlias}")
            ->leftJoin("routemaster as {$routeAlias}", "{$routeAlias}.routecode", '=', "{$cocAlias}.routecode")
            ->leftJoin("customermaster as {$customerAlias}", "{$customerAlias}.customercode", '=', "{$cocAlias}.customercode")
            ->selectRaw('
                ' . $qualifiedCocAlias . '.primary_id,
                ' . $qualifiedCocAlias . '.routecode,
                COALESCE(' . $qualifiedRouteAlias . '.routename, "") as routename,
                COALESCE(' . $qualifiedRouteAlias . '.arbroutename, "") as arbroutename,
                ' . $qualifiedCocAlias . '.salesmancode,
                DATE(' . $qualifiedCocAlias . '.visitstartdate) as visitdate,
                ' . $qualifiedCocAlias . '.customercode,
                COALESCE(' . $qualifiedCustomerAlias . '.alternatecode, "") as alternatecode,
                COALESCE(' . $qualifiedCustomerAlias . '.customername, "") as customername,
                COALESCE(' . $qualifiedCustomerAlias . '.arbcustomername, "") as arbcustomername
            ')
            ->whereDate("{$cocAlias}.visitstartdate", $selectedDate);

        $scope->scopeQuery($user, $query, 'route', "{$cocAlias}.routecode");

        if ($search !== '') {
            $query->where(function ($searchQuery) use ($search, $cocAlias, $routeAlias, $customerAlias) {
                $like = '%' . $search . '%';

                $searchQuery
                    ->where("{$cocAlias}.routecode", 'like', $like)
                    ->orWhere("{$routeAlias}.routename", 'like', $like)
                    ->orWhere("{$routeAlias}.arbroutename", 'like', $like)
                    ->orWhere("{$cocAlias}.salesmancode", 'like', $like)
                    ->orWhere("{$cocAlias}.customercode", 'like', $like)
                    ->orWhere("{$customerAlias}.alternatecode", 'like', $like)
                    ->orWhere("{$customerAlias}.customername", 'like', $like)
                    ->orWhere("{$customerAlias}.arbcustomername", 'like', $like);
            });
        }

        $documents = $query
            ->orderBy($this->sortColumn($sortBy, $cocAlias, $routeAlias, $customerAlias), $sortDir)
            ->orderBy("{$cocAlias}.primary_id", 'desc')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn ($row) => [
                'primary_id' => (int) $row->primary_id,
                'routecode' => (int) $row->routecode,
                'routename' => $row->routename,
                'arbroutename' => $row->arbroutename,
                'salesmancode' => (int) $row->salesmancode,
                'visitdate' => $this->formatDate($row->visitdate),
                'customercode' => (int) $row->customercode,
                'alternatecode' => $row->alternatecode,
                'customername' => $row->customername,
                'arbcustomername' => $row->arbcustomername,
            ]);

        return Inertia::render('transaction/customer-inventory/Index', [
            'documents' => $documents,
            'filters' => [
                'date' => $selectedDate,
                'search' => $search,
                'per_page' => $perPage,
                'sort_by' => $sortBy,
                'sort_dir' => $sortDir,
            ],
        ]);
    }

    public function show(Request $request, int $primaryId): Response
    {
        abort_unless($this->hasOverviewTables(), 404);
        $cocAlias = 'coc';
        $routeAlias = 'route';
        $customerAlias = 'customer';
        $qualifiedCocAlias = DB::getTablePrefix() . $cocAlias;
        $qualifiedRouteAlias = DB::getTablePrefix() . $routeAlias;
        $qualifiedCustomerAlias = DB::getTablePrefix() . $customerAlias;

        $header = DB::table("customeroperationscontrol as {$cocAlias}")
            ->leftJoin("routemaster as {$routeAlias}", "{$routeAlias}.routecode", '=', "{$cocAlias}.routecode")
            ->leftJoin("customermaster as {$customerAlias}", "{$customerAlias}.customercode", '=', "{$cocAlias}.customercode")
            ->where("{$cocAlias}.primary_id", $primaryId)
            ->selectRaw('
                ' . $qualifiedCocAlias . '.primary_id,
                ' . $qualifiedCocAlias . '.routekey,
                ' . $qualifiedCocAlias . '.visitkey,
                ' . $qualifiedCocAlias . '.routecode,
                ' . $qualifiedCocAlias . '.salesmancode,
                DATE(' . $qualifiedCocAlias . '.visitstartdate) as visitdate,
                COALESCE(' . $qualifiedRouteAlias . '.routename, "") as routename,
                COALESCE(' . $qualifiedRouteAlias . '.arbroutename, "") as arbroutename,
                ' . $qualifiedCocAlias . '.customercode,
                COALESCE(' . $qualifiedCustomerAlias . '.alternatecode, "") as alternatecode,
                COALESCE(' . $qualifiedCustomerAlias . '.customername, "") as customername,
                COALESCE(' . $qualifiedCustomerAlias . '.arbcustomername, "") as arbcustomername
            ')
            ->first();

        abort_unless($header, 404);
        abort_unless(app(AccessScopeService::class)->allows($request->user(), 'route', $header->routecode ?? null), 403);

        $lines = collect();
        if ($this->hasDetailTables()) {
            $detailAlias = 'cid';
            $itemAlias = 'item';
            $qualifiedDetailAlias = DB::getTablePrefix() . $detailAlias;
            $qualifiedItemAlias = DB::getTablePrefix() . $itemAlias;

            $lines = DB::table("customerinventorydetail as {$detailAlias}")
                ->join("itemmaster as {$itemAlias}", "{$itemAlias}.actualitemcode", '=', "{$detailAlias}.itemcode")
                ->where("{$detailAlias}.routekey", $header->routekey)
                ->where("{$detailAlias}.visitkey", $header->visitkey)
                ->selectRaw('
                    ' . $qualifiedDetailAlias . '.itemcode,
                    COALESCE(' . $qualifiedItemAlias . '.alternatecode, "") as alternatecode,
                    ' . $this->itemDescriptionExpression($qualifiedItemAlias) . ' as description,
                    ' . $this->unitsPerCaseExpression($qualifiedItemAlias) . ' as unitspercase,
                    COALESCE(' . $qualifiedDetailAlias . '.qtyloc1case, 0) as qtyloc1case,
                    COALESCE(' . $qualifiedDetailAlias . '.qtyloc1each, 0) as qtyloc1each,
                    COALESCE(' . $qualifiedDetailAlias . '.qtyloc2case, 0) as qtyloc2case,
                    COALESCE(' . $qualifiedDetailAlias . '.qtyloc2each, 0) as qtyloc2each,
                    COALESCE(' . $qualifiedDetailAlias . '.qtyloc3case, 0) as qtyloc3case,
                    COALESCE(' . $qualifiedDetailAlias . '.qtyloc3each, 0) as qtyloc3each
                ')
                ->orderBy("{$detailAlias}.itemcode")
                ->get()
                ->map(fn ($row) => [
                    'itemcode' => (int) $row->itemcode,
                    'display_code' => $this->displayItemCode($row),
                    'description' => $row->description ?? '',
                    'upc' => max(1, (int) ($row->unitspercase ?? 1)),
                    'location1' => $this->locationLabel($row->qtyloc1case, $row->qtyloc1each),
                    'location2' => $this->locationLabel($row->qtyloc2case, $row->qtyloc2each),
                    'location3' => $this->locationLabel($row->qtyloc3case, $row->qtyloc3each),
                ])
                ->values();
        }

        return Inertia::render('transaction/customer-inventory/Show', [
            'header' => [
                'primary_id' => (int) $header->primary_id,
                'routecode' => (int) $header->routecode,
                'routename' => $header->routename,
                'arbroutename' => $header->arbroutename,
                'salesmancode' => (int) $header->salesmancode,
                'visitdate' => $this->formatDate($header->visitdate),
                'customercode' => (int) $header->customercode,
                'alternatecode' => $header->alternatecode,
                'customername' => $header->customername,
                'arbcustomername' => $header->arbcustomername,
            ],
            'lines' => $lines,
            'filters' => [
                'date' => (string) $request->input('date', ''),
                'search' => (string) $request->input('search', ''),
                'page' => max(1, (int) $request->input('page', 1)),
                'per_page' => max(10, (int) $request->input('per_page', 10)),
                'sort_by' => (string) $request->input('sort_by', 'visitdate'),
                'sort_dir' => (string) $request->input('sort_dir', 'desc'),
            ],
        ]);
    }

    private function hasOverviewTables(): bool
    {
        return Schema::hasTable('customeroperationscontrol')
            && Schema::hasTable('routemaster')
            && Schema::hasTable('customermaster');
    }

    private function hasDetailTables(): bool
    {
        return Schema::hasTable('customerinventorydetail')
            && Schema::hasTable('itemmaster');
    }

    private function selectedDate(Request $request): Carbon
    {
        $date = (string) $request->input('date', '');

        try {
            return $date !== '' ? Carbon::parse($date) : now();
        } catch (\Throwable) {
            return now();
        }
    }

    private function emptyPaginator(Request $request, int $perPage): LengthAwarePaginator
    {
        return new \Illuminate\Pagination\LengthAwarePaginator(
            [],
            0,
            $perPage,
            max(1, (int) $request->input('page', 1)),
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );
    }

    private function sortColumn(string $sortBy, string $cocAlias, string $routeAlias, string $customerAlias): string
    {
        return match ($sortBy) {
            'routecode' => "{$cocAlias}.routecode",
            'routename' => "{$routeAlias}.routename",
            'salesmancode' => "{$cocAlias}.salesmancode",
            'customercode' => "{$cocAlias}.customercode",
            'alternatecode' => "{$customerAlias}.alternatecode",
            'customername' => "{$customerAlias}.customername",
            default => "{$cocAlias}.visitstartdate",
        };
    }

    private function formatDate(mixed $value): string
    {
        if (!$value) {
            return '';
        }

        try {
            return Carbon::parse($value)->format('d-m-Y');
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    private function itemDescriptionExpression(string $alias): string
    {
        $descriptionColumns = [];

        if (Schema::hasColumn('itemmaster', 'itemshortdescription')) {
            $descriptionColumns[] = $alias . '.itemshortdescription';
        }

        if (Schema::hasColumn('itemmaster', 'itemdescription')) {
            $descriptionColumns[] = $alias . '.itemdescription';
        }

        if (empty($descriptionColumns)) {
            return '""';
        }

        return 'COALESCE(' . implode(', ', $descriptionColumns) . ', "")';
    }

    private function unitsPerCaseExpression(string $alias): string
    {
        return Schema::hasColumn('itemmaster', 'unitspercase')
            ? 'COALESCE(' . $alias . '.unitspercase, 1)'
            : '1';
    }

    private function displayItemCode(object $row): string
    {
        if ($this->useAlternateCode() && filled($row->alternatecode ?? '')) {
            return (string) $row->alternatecode;
        }

        return (string) $row->itemcode;
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

    private function locationLabel(mixed $cases, mixed $each): string
    {
        return (string) ((int) ($cases ?? 0)) . '/' . (string) ((int) ($each ?? 0));
    }
}
