<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutoJpPlanItem extends Model
{
    protected $table = 'autojp_plan_items';

    protected $fillable = [
        'plan_id',
        'customercode',
        'home_routecode',
        'assigned_routecode',
        'assigned_weekday',
        'assigned_sequence',
        'delivery_slot_from',
        'delivery_slot_to',
        'planned_start_time',
        'planned_end_time',
        'last_invoice_date',
        'last_order_date',
        'serviced_visits',
        'scheduled_visits',
        'avg_visit_start_time',
        'avg_visit_duration_minutes',
        'preferred_weekday',
        'score',
        'source',
        'generation_notes',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(AutoJpPlanHeader::class, 'plan_id');
    }
}
