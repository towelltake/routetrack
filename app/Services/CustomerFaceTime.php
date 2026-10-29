<?php

namespace App\Services;

use App\Models\CustomerMaster;
use Illuminate\Support\Collection;

class CustomerFaceTime
{
    /** Resolve targets only for planned customers with no visit record in that journey. */
    public function unvisitedPlans(Collection $plans, Collection $visits): Collection
    {
        $visited = $visits->mapWithKeys(fn ($visit) => [$visit->routekey.':'.$visit->customercode => true]);
        $missing = $plans->unique(fn ($plan) => $plan->routekey.':'.$plan->customercode)
            ->reject(fn ($plan) => $visited->has($plan->routekey.':'.$plan->customercode));
        $targets = $this->fallbackMinutesFor($missing->pluck('customercode'));
        return $missing->map(function ($plan) use ($targets) {
            $row = clone $plan;
            $row->expected_minutes = $targets->get($plan->customercode, 0.0);
            return $row;
        })->values();
    }

    public function fallbackMinutesFor(Collection $codes): Collection
    {
        if ($codes->isEmpty()) return collect();
        return CustomerMaster::query()
            ->leftJoin('customerclustermapping as cft_mapping', function ($join) {
                $join->on('cft_mapping.divisioncode', '=', 'customermaster.DivisionCode')
                    ->on('cft_mapping.channel', '=', 'customermaster.channel');
            })->whereIn('customermaster.customercode', $codes->unique()->values())
            ->select('customermaster.customercode')
            ->selectRaw('CASE WHEN customermaster.customerfacetime > 0 THEN customermaster.customerfacetime
                WHEN cft_mapping.cft > 0 THEN cft_mapping.cft ELSE 0 END as planned_minutes')
            ->get()->mapWithKeys(fn ($customer) => [$customer->customercode => self::plannedMinutes($customer->planned_minutes)]);
    }

    public static function qualifies(?float $actualMinutes, mixed $plannedMinutes, bool $hasOtp): bool
    {
        $planned = self::plannedMinutes($plannedMinutes);
        return !$hasOtp && $planned > 0 && $actualMinutes !== null && $actualMinutes >= $planned;
    }

    /** Planned minutes recorded on this visit; never fall back to current master settings. */
    public static function plannedMinutes(mixed $cft): float
    {
        return is_numeric($cft) ? max(0, (float) $cft) : 0.0;
    }
}
