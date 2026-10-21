<?php

namespace App\Services;

class CustomerFaceTime
{
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
