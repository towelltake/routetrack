<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AutoJpPlanHeader extends Model
{
    protected $table = 'autojp_plan_headers';

    protected $fillable = [
        'routecode',
        'week_number',
        'route_type',
        'work_start_time',
        'work_end_time',
        'working_days',
        'lookback_weeks',
        'status',
        'customer_count',
        'external_customer_count',
        'generated_by',
        'generated_at',
        'published_by',
        'published_at',
    ];

    protected $casts = [
        'generated_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(AutoJpPlanItem::class, 'plan_id');
    }
}
