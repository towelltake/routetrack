<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserAccessCode;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AccessScopeService
{
    private const LEVELS = [
        'company' => 'cmpycode',
        'country' => 'countrycode',
        'region' => 'regionmstcode',
        'depot' => 'depotcode',
        'area' => 'areacode',
        'subarea' => 'subareacode',
        'route' => 'routecode',
    ];

    /** @var array<string, array<string, Collection|null>> */
    private array $cache = [];

    public function isScoped(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        $accessType = (int) ($user->accesstypeid ?? 0);

        return $accessType >= 1
            && $accessType <= 6
            && UserAccessCode::where('username', $user->username)->exists();
    }

    public function ids(?User $user, string $level): ?Collection
    {
        if (! $user || ! $this->isScoped($user)) {
            return null;
        }

        $level = strtolower(trim($level));

        if (! array_key_exists($level, self::LEVELS)) {
            return collect();
        }

        $cacheKey = $user->username . ':' . (int) ($user->accesstypeid ?? 0);

        if (isset($this->cache[$cacheKey][$level])) {
            return $this->cache[$cacheKey][$level];
        }

        return $this->cache[$cacheKey][$level] = $this->resolveIds($user, $level);
    }

    public function allows(?User $user, string $level, int|string|null $id): bool
    {
        if ($id === null || $id === '') {
            return true;
        }

        $ids = $this->ids($user, $level);

        if ($ids === null) {
            return true;
        }

        return $ids->contains((int) $id);
    }

    public function scopeQuery(?User $user, EloquentBuilder|QueryBuilder $query, string $level, string $column): EloquentBuilder|QueryBuilder
    {
        $ids = $this->ids($user, $level);

        if ($ids === null) {
            return $query;
        }

        if ($ids->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn($column, $ids->all());
    }

    private function resolveIds(User $user, string $level): Collection
    {
        $accessType = (int) ($user->accesstypeid ?? 0);

        return match ($level) {
            'company' => $this->companyIds($user, $accessType),
            'country' => $this->countryIds($user, $accessType),
            'region' => $this->regionIds($user, $accessType),
            'depot' => $this->depotIds($user, $accessType),
            'area' => $this->areaIds($user, $accessType),
            'subarea' => $this->subAreaIds($user, $accessType),
            'route' => $this->routeIds($user, $accessType),
            default => collect(),
        };
    }

    private function companyIds(User $user, int $accessType): Collection
    {
        return match ($accessType) {
            1 => $this->directIds($user, 'cmpycode'),
            2 => DB::table('country')->whereIn('countrycode', $this->directIds($user, 'countrycode'))->pluck('cmpycode'),
            3 => DB::table('regionmaster as region')
                ->join('country as country', 'country.countrycode', '=', 'region.countrycode')
                ->whereIn('region.regionmstcode', $this->directIds($user, 'regionmstcode'))
                ->pluck('country.cmpycode'),
            4 => DB::table('depotmaster')->whereIn('depotcode', $this->directIds($user, 'depotcode'))->pluck('cmpycode'),
            5 => DB::table('areamaster as area')
                ->join('depotmaster as depot', 'depot.depotcode', '=', 'area.depotcode')
                ->whereIn('area.areacode', $this->directIds($user, 'areacode'))
                ->pluck('depot.cmpycode'),
            6 => DB::table('subareamaster as sub')
                ->join('areamaster as area', 'area.areacode', '=', 'sub.areacode')
                ->join('depotmaster as depot', 'depot.depotcode', '=', 'area.depotcode')
                ->whereIn('sub.subareacode', $this->directIds($user, 'subareacode'))
                ->pluck('depot.cmpycode'),
            default => collect(),
        };
    }

    private function countryIds(User $user, int $accessType): Collection
    {
        return match ($accessType) {
            1 => DB::table('country')
                ->whereIn('cmpycode', $this->companyIds($user, 1))
                ->pluck('countrycode')
                ->merge(
                    DB::table('company')
                        ->whereIn('cmpycode', $this->companyIds($user, 1))
                        ->whereNotNull('countrycode')
                        ->pluck('countrycode')
                )
                ->map(fn ($value) => (int) $value)
                ->filter()
                ->unique()
                ->values(),
            2 => $this->directIds($user, 'countrycode'),
            3 => DB::table('regionmaster')->whereIn('regionmstcode', $this->directIds($user, 'regionmstcode'))->pluck('countrycode'),
            4 => DB::table('depotmaster as depot')
                ->join('regionmaster as region', 'region.regionmstcode', '=', 'depot.regionmstcode')
                ->whereIn('depot.depotcode', $this->directIds($user, 'depotcode'))
                ->pluck('region.countrycode'),
            5 => DB::table('areamaster as area')
                ->join('depotmaster as depot', 'depot.depotcode', '=', 'area.depotcode')
                ->join('regionmaster as region', 'region.regionmstcode', '=', 'depot.regionmstcode')
                ->whereIn('area.areacode', $this->directIds($user, 'areacode'))
                ->pluck('region.countrycode'),
            6 => DB::table('subareamaster as sub')
                ->join('areamaster as area', 'area.areacode', '=', 'sub.areacode')
                ->join('depotmaster as depot', 'depot.depotcode', '=', 'area.depotcode')
                ->join('regionmaster as region', 'region.regionmstcode', '=', 'depot.regionmstcode')
                ->whereIn('sub.subareacode', $this->directIds($user, 'subareacode'))
                ->pluck('region.countrycode'),
            default => collect(),
        };
    }

    private function regionIds(User $user, int $accessType): Collection
    {
        return match ($accessType) {
            1 => DB::table('regionmaster')
                ->whereIn('countrycode', $this->countryIds($user, 1))
                ->pluck('regionmstcode'),
            2 => DB::table('regionmaster')->whereIn('countrycode', $this->directIds($user, 'countrycode'))->pluck('regionmstcode'),
            3 => $this->directIds($user, 'regionmstcode'),
            4 => DB::table('depotmaster')->whereIn('depotcode', $this->directIds($user, 'depotcode'))->pluck('regionmstcode'),
            5 => DB::table('areamaster as area')
                ->join('depotmaster as depot', 'depot.depotcode', '=', 'area.depotcode')
                ->whereIn('area.areacode', $this->directIds($user, 'areacode'))
                ->pluck('depot.regionmstcode'),
            6 => DB::table('subareamaster as sub')
                ->join('areamaster as area', 'area.areacode', '=', 'sub.areacode')
                ->join('depotmaster as depot', 'depot.depotcode', '=', 'area.depotcode')
                ->whereIn('sub.subareacode', $this->directIds($user, 'subareacode'))
                ->pluck('depot.regionmstcode'),
            default => collect(),
        };
    }

    private function depotIds(User $user, int $accessType): Collection
    {
        return match ($accessType) {
            1 => DB::table('depotmaster')
                ->where(function ($query) use ($user) {
                    $query->whereIn('cmpycode', $this->companyIds($user, 1))
                        ->orWhereIn('regionmstcode', $this->regionIds($user, 1));
                })
                ->pluck('depotcode'),
            2 => DB::table('depotmaster as depot')
                ->join('regionmaster as region', 'region.regionmstcode', '=', 'depot.regionmstcode')
                ->whereIn('region.countrycode', $this->directIds($user, 'countrycode'))
                ->pluck('depot.depotcode'),
            3 => DB::table('depotmaster')->whereIn('regionmstcode', $this->directIds($user, 'regionmstcode'))->pluck('depotcode'),
            4 => $this->directIds($user, 'depotcode'),
            5 => DB::table('areamaster')->whereIn('areacode', $this->directIds($user, 'areacode'))->pluck('depotcode'),
            6 => DB::table('subareamaster as sub')
                ->join('areamaster as area', 'area.areacode', '=', 'sub.areacode')
                ->whereIn('sub.subareacode', $this->directIds($user, 'subareacode'))
                ->pluck('area.depotcode'),
            default => collect(),
        };
    }

    private function areaIds(User $user, int $accessType): Collection
    {
        return match ($accessType) {
            1 => DB::table('areamaster as area')
                ->join('depotmaster as depot', 'depot.depotcode', '=', 'area.depotcode')
                ->where(function ($query) use ($user) {
                    $query->whereIn('depot.cmpycode', $this->companyIds($user, 1))
                        ->orWhereIn('depot.regionmstcode', $this->regionIds($user, 1));
                })
                ->pluck('area.areacode'),
            2 => DB::table('areamaster as area')
                ->join('depotmaster as depot', 'depot.depotcode', '=', 'area.depotcode')
                ->join('regionmaster as region', 'region.regionmstcode', '=', 'depot.regionmstcode')
                ->whereIn('region.countrycode', $this->directIds($user, 'countrycode'))
                ->pluck('area.areacode'),
            3 => DB::table('areamaster as area')
                ->join('depotmaster as depot', 'depot.depotcode', '=', 'area.depotcode')
                ->whereIn('depot.regionmstcode', $this->directIds($user, 'regionmstcode'))
                ->pluck('area.areacode'),
            4 => DB::table('areamaster')->whereIn('depotcode', $this->directIds($user, 'depotcode'))->pluck('areacode'),
            5 => $this->directIds($user, 'areacode'),
            6 => DB::table('subareamaster')->whereIn('subareacode', $this->directIds($user, 'subareacode'))->pluck('areacode'),
            default => collect(),
        };
    }

    private function subAreaIds(User $user, int $accessType): Collection
    {
        return match ($accessType) {
            1 => DB::table('subareamaster as sub')
                ->join('areamaster as area', 'area.areacode', '=', 'sub.areacode')
                ->join('depotmaster as depot', 'depot.depotcode', '=', 'area.depotcode')
                ->where(function ($query) use ($user) {
                    $query->whereIn('depot.cmpycode', $this->companyIds($user, 1))
                        ->orWhereIn('depot.regionmstcode', $this->regionIds($user, 1));
                })
                ->pluck('sub.subareacode'),
            2 => DB::table('subareamaster as sub')
                ->join('areamaster as area', 'area.areacode', '=', 'sub.areacode')
                ->join('depotmaster as depot', 'depot.depotcode', '=', 'area.depotcode')
                ->join('regionmaster as region', 'region.regionmstcode', '=', 'depot.regionmstcode')
                ->whereIn('region.countrycode', $this->directIds($user, 'countrycode'))
                ->pluck('sub.subareacode'),
            3 => DB::table('subareamaster as sub')
                ->join('areamaster as area', 'area.areacode', '=', 'sub.areacode')
                ->join('depotmaster as depot', 'depot.depotcode', '=', 'area.depotcode')
                ->whereIn('depot.regionmstcode', $this->directIds($user, 'regionmstcode'))
                ->pluck('sub.subareacode'),
            4 => DB::table('subareamaster as sub')
                ->join('areamaster as area', 'area.areacode', '=', 'sub.areacode')
                ->whereIn('area.depotcode', $this->directIds($user, 'depotcode'))
                ->pluck('sub.subareacode'),
            5 => DB::table('subareamaster')->whereIn('areacode', $this->directIds($user, 'areacode'))->pluck('subareacode'),
            6 => $this->directIds($user, 'subareacode'),
            default => collect(),
        };
    }

    private function routeIds(User $user, int $accessType): Collection
    {
        return match ($accessType) {
            1 => DB::table('routemaster as route')
                ->leftJoin('subareamaster as sub', 'sub.subareacode', '=', 'route.subareacode')
                ->leftJoin('areamaster as area', 'area.areacode', '=', 'sub.areacode')
                ->leftJoin('depotmaster as depot', 'depot.depotcode', '=', 'area.depotcode')
                ->where(function ($query) use ($user) {
                    $companyIds = $this->companyIds($user, 1)->all();
                    $regionIds = $this->regionIds($user, 1)->all();

                    $query->whereIn('route.cmpycode', $companyIds)
                        ->orWhereIn('route.regionmstcode', $regionIds)
                        ->orWhere(function ($inner) use ($companyIds) {
                            $inner->whereNull('route.cmpycode')
                                ->whereIn('depot.cmpycode', $companyIds);
                        })
                        ->orWhere(function ($inner) use ($regionIds) {
                            $inner->whereNull('route.cmpycode')
                                ->whereIn('depot.regionmstcode', $regionIds);
                        });
                })
                ->pluck('route.routecode'),
            2 => DB::table('routemaster as route')
                ->leftJoin('subareamaster as sub', 'sub.subareacode', '=', 'route.subareacode')
                ->leftJoin('areamaster as area', 'area.areacode', '=', 'sub.areacode')
                ->leftJoin('depotmaster as depot', 'depot.depotcode', '=', 'area.depotcode')
                ->leftJoin('regionmaster as region', 'region.regionmstcode', '=', 'depot.regionmstcode')
                ->whereIn('region.countrycode', $this->directIds($user, 'countrycode'))
                ->pluck('route.routecode'),
            3 => DB::table('routemaster')->whereIn('regionmstcode', $this->directIds($user, 'regionmstcode'))->pluck('routecode'),
            4 => DB::table('routemaster as route')
                ->join('subareamaster as sub', 'sub.subareacode', '=', 'route.subareacode')
                ->join('areamaster as area', 'area.areacode', '=', 'sub.areacode')
                ->whereIn('area.depotcode', $this->directIds($user, 'depotcode'))
                ->pluck('route.routecode'),
            5 => DB::table('routemaster as route')
                ->join('subareamaster as sub', 'sub.subareacode', '=', 'route.subareacode')
                ->whereIn('sub.areacode', $this->directIds($user, 'areacode'))
                ->pluck('route.routecode'),
            6 => DB::table('routemaster')->whereIn('subareacode', $this->directIds($user, 'subareacode'))->pluck('routecode'),
            default => collect(),
        };
    }

    private function directIds(User $user, string $column): Collection
    {
        return UserAccessCode::query()
            ->where('username', $user->username)
            ->whereNotNull($column)
            ->pluck($column)
            ->map(fn ($value) => (int) $value)
            ->filter()
            ->unique()
            ->values();
    }
}
