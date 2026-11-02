<?php

namespace App\Services;

use Illuminate\Support\Collection;

class CustomerFaceTime
{
    /** Resolve targets only for planned customers with no visit record in that journey. */
    public function unvisitedPlans(Collection $plans, Collection $visits): Collection
    {
        $visited = $visits->mapWithKeys(fn ($visit) => [$visit->routekey.':'.$visit->customercode => true]);
        $missing = $plans->unique(fn ($plan) => $plan->routekey.':'.$plan->customercode)
            ->reject(fn ($plan) => $visited->has($plan->routekey.':'.$plan->customercode));

        return $missing->map(function ($plan) {
            $row = clone $plan;
            $row->expected_minutes = self::plannedMinutes($plan->cft ?? null);
            return $row;
        })->values();
    }

    public static function qualifies(?float $actualMinutes, mixed $plannedMinutes, bool $hasOtp, bool $onPremise): bool
    {
        $planned = self::plannedMinutes($plannedMinutes);
        return $onPremise && !$hasOtp && $planned > 0 && $actualMinutes !== null && $actualMinutes >= $planned;
    }

    /** Planned minutes recorded on this visit; never fall back to current master settings. */
    public static function plannedMinutes(mixed $cft): float
    {
        return is_numeric($cft) ? max(0, (float) $cft) : 0.0;
    }
}
