<?php

return [
    'stationary_minutes' => max(1, (float) env('TRACKING_STATIONARY_MINUTES', 5)),
    'stationary_radius_m' => max(1, (float) env('TRACKING_STATIONARY_RADIUS_M', 30)),
    'stationary_max_gap_seconds' => max(1, (int) env('TRACKING_STATIONARY_MAX_GAP_SECONDS', 120)),
    'max_accuracy_m' => max(1, (float) env('TRACKING_MAX_ACCURACY_M', 50)),
];
