<?php

namespace App\Services\Reports;

use App\Models\User;
use App\Models\UserAccessCode;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReportScopeService
{
    private const ACCESS_TYPES = [
        1 => 'Company',
        2 => 'Country',
        3 => 'Region',
        4 => 'Depot',
        5 => 'Area',
        6 => 'Sub Area',
    ];

    public function resolve(User $user, array $filters = []): array
    {
        $routeRows = $this->routeScopeRows($user);

        $scopedRows = $routeRows
            ->when($this->nullableInt($filters['cmpycode'] ?? null), fn (Collection $rows, int $value) => $rows->where('cmpycode', $value))
            ->when($this->nullableInt($filters['regionmstcode'] ?? null), fn (Collection $rows, int $value) => $rows->where('regionmstcode', $value))
            ->when($this->nullableInt($filters['depotcode'] ?? null), fn (Collection $rows, int $value) => $rows->where('depotcode', $value))
            ->when($this->nullableInt($filters['areacode'] ?? null), fn (Collection $rows, int $value) => $rows->where('areacode', $value))
            ->when($this->nullableInt($filters['subareacode'] ?? null), fn (Collection $rows, int $value) => $rows->where('subareacode', $value))
            ->when($this->nullableInt($filters['routecode'] ?? null), fn (Collection $rows, int $value) => $rows->where('routecode', $value))
            ->values();

        $routecodes = $scopedRows
            ->pluck('routecode')
            ->filter()
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values()
            ->all();
        $companyOptions = $this->companyOptions($user, $routeRows);

        return [
            'routecodes' => $routecodes,
            'rows' => $routeRows,
            'scoped_rows' => $scopedRows,
            'matches_none' => $scopedRows->isEmpty(),
            'limited' => $this->userIsScoped($user),
            'access_type' => self::ACCESS_TYPES[(int) ($user->accesstypeid ?? 0)] ?? 'All Routes',
            'options' => [
                'companies' => $companyOptions,
                'regions' => $this->distinctOptions($routeRows, 'regionmstcode', 'region_label'),
                'depots' => $this->distinctOptions($routeRows, 'depotcode', 'depot_label'),
                'areas' => $this->distinctOptions($routeRows, 'areacode', 'area_label'),
                'subAreas' => $this->distinctOptions($routeRows, 'subareacode', 'subarea_label'),
                'routes' => $this->distinctOptions($routeRows, 'routecode', 'route_label'),
            ],
        ];
    }

    private function routeScopeRows(User $user): Collection
    {
        if (!Schema::hasTable('routemaster')) {
            return collect();
        }

        $route = $this->qualifiedAlias('route');
        $sub = $this->qualifiedAlias('sub');
        $area = $this->qualifiedAlias('area');
        $depot = $this->qualifiedAlias('depot');
        $routeCompany = $this->qualifiedAlias('route_company');
        $depotCompany = $this->qualifiedAlias('depot_company');
        $region = $this->qualifiedAlias('region');

        $query = DB::table('routemaster as route')
            ->leftJoin('subareamaster as sub', 'sub.subareacode', '=', 'route.subareacode')
            ->leftJoin('areamaster as area', 'area.areacode', '=', 'sub.areacode')
            ->leftJoin('depotmaster as depot', 'depot.depotcode', '=', 'area.depotcode')
            ->leftJoin('company as route_company', 'route_company.cmpycode', '=', 'route.cmpycode')
            ->leftJoin('company as depot_company', 'depot_company.cmpycode', '=', 'depot.cmpycode')
            ->leftJoin('regionmaster as region', 'region.regionmstcode', '=', 'route.regionmstcode')
            ->selectRaw("
                {$route}.routecode,
                COALESCE({$route}.routename, '') as routename,
                COALESCE({$route}.arbroutename, '') as arbroutename,
                COALESCE({$route}.cmpycode, {$depotCompany}.cmpycode, 0) as cmpycode,
                COALESCE({$routeCompany}.name, {$depotCompany}.name, '') as company_name,
                COALESCE({$route}.regionmstcode, 0) as regionmstcode,
                COALESCE({$region}.regionmstname, '') as regionmstname,
                COALESCE({$region}.countrycode, 0) as countrycode,
                COALESCE({$depot}.depotcode, 0) as depotcode,
                COALESCE({$depot}.depotname, '') as depotname,
                COALESCE({$area}.areacode, 0) as areacode,
                COALESCE({$area}.areaname, '') as areaname,
                COALESCE({$sub}.subareacode, 0) as subareacode,
                COALESCE({$sub}.subareaname, '') as subareaname
            ")
            ->when(
                Schema::hasColumn('routemaster', 'routetmpl'),
                fn ($builder) => $builder->where('route.routetmpl', 0)
            )
            ->when(
                Schema::hasColumn('routemaster', 'activestatus'),
                fn ($builder) => $builder->where('route.activestatus', 1)
            );

        $accessType = (int) ($user->accesstypeid ?? 0);
        $accessRows = UserAccessCode::where('username', $user->username)->get();

        if ($this->userIsScoped($user) && $accessRows->isNotEmpty()) {
            $ids = (match ($accessType) {
                1 => $accessRows->pluck('cmpycode'),
                2 => $accessRows->pluck('countrycode'),
                3 => $accessRows->pluck('regionmstcode'),
                4 => $accessRows->pluck('depotcode'),
                5 => $accessRows->pluck('areacode'),
                6 => $accessRows->pluck('subareacode'),
                default => collect(),
            })->filter()->map(fn ($value) => (int) $value)->unique()->values();

            $query->when($ids->isNotEmpty(), function ($builder) use ($accessType, $ids, $route, $depotCompany) {
                match ($accessType) {
                    1 => $builder->whereIn(DB::raw("COALESCE({$route}.cmpycode, {$depotCompany}.cmpycode)"), $ids->all()),
                    2 => $builder->whereIn('region.countrycode', $ids->all()),
                    3 => $builder->whereIn('route.regionmstcode', $ids->all()),
                    4 => $builder->whereIn('depot.depotcode', $ids->all()),
                    5 => $builder->whereIn('area.areacode', $ids->all()),
                    6 => $builder->whereIn('sub.subareacode', $ids->all()),
                    default => null,
                };
            });
        }

        return $query->orderBy('route.routecode')->get()->map(function ($row) {
            $routeName = trim((string) ($row->routename ?: $row->arbroutename));

            return [
                'routecode' => (int) ($row->routecode ?? 0),
                'cmpycode' => (int) ($row->cmpycode ?? 0),
                'regionmstcode' => (int) ($row->regionmstcode ?? 0),
                'countrycode' => (int) ($row->countrycode ?? 0),
                'depotcode' => (int) ($row->depotcode ?? 0),
                'areacode' => (int) ($row->areacode ?? 0),
                'subareacode' => (int) ($row->subareacode ?? 0),
                'company_label' => $this->label($row->cmpycode, $row->company_name),
                'region_label' => $this->label($row->regionmstcode, $row->regionmstname),
                'depot_label' => $this->label($row->depotcode, $row->depotname),
                'area_label' => $this->label($row->areacode, $row->areaname),
                'subarea_label' => $this->label($row->subareacode, $row->subareaname),
                'route_label' => trim(((int) ($row->routecode ?? 0)) . ' - ' . $routeName),
            ];
        });
    }

    private function distinctOptions(Collection $rows, string $valueKey, string $labelKey): array
    {
        return $rows
            ->filter(fn (array $row) => !empty($row[$valueKey]))
            ->unique($valueKey)
            ->sortBy($valueKey)
            ->map(fn (array $row) => [
                'id' => (int) $row[$valueKey],
                'label' => (string) $row[$labelKey],
            ])
            ->values()
            ->all();
    }

    private function companyOptions(User $user, Collection $routeRows): array
    {
        $options = collect($this->distinctOptions($routeRows, 'cmpycode', 'company_label'));

        if ((int) ($user->accesstypeid ?? 0) !== 1 || ! $this->userIsScoped($user) || ! Schema::hasTable('company')) {
            return $options->all();
        }

        $accessOptions = UserAccessCode::query()
            ->where('username', $user->username)
            ->whereNotNull('cmpycode')
            ->select('cmpycode')
            ->distinct()
            ->orderBy('cmpycode')
            ->get()
            ->map(function ($row) {
                $company = DB::table('company')
                    ->where('cmpycode', (int) $row->cmpycode)
                    ->select('cmpycode', 'name')
                    ->first();

                if (! $company) {
                    return null;
                }

                return [
                    'id' => (int) $company->cmpycode,
                    'label' => $this->label($company->cmpycode, $company->name),
                ];
            })
            ->filter();

        return $options
            ->concat($accessOptions)
            ->unique('id')
            ->sortBy('id')
            ->values()
            ->all();
    }

    private function label(mixed $id, mixed $name): string
    {
        $id = (int) ($id ?? 0);
        $name = trim((string) ($name ?? ''));

        if ($id <= 0 && $name === '') {
            return '';
        }

        return $name === '' ? (string) $id : trim($id . ' - ' . $name);
    }

    private function userIsScoped(User $user): bool
    {
        return (int) ($user->accesstypeid ?? 0) >= 1
            && (int) ($user->accesstypeid ?? 0) <= 6
            && UserAccessCode::where('username', $user->username)->exists();
    }

    private function qualifiedAlias(string $alias): string
    {
        return DB::getQueryGrammar()->wrapTable($alias);
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
