<?php

namespace App\Services;

use App\Models\CustomerMaster;
use Illuminate\Support\Collection;

class CustomerFaceTime
{
    /** Resolve current planned minutes once per active customer, not per visit. */
    public function minutesFor(Collection $customerCodes): Collection
    {
        if ($customerCodes->isEmpty()) return collect();

        return CustomerMaster::query()
            ->leftJoin('customerclustermapping as cft_mapping', function ($join) {
                $join->on('cft_mapping.divisioncode', '=', 'customermaster.DivisionCode')
                    ->on('cft_mapping.channel', '=', 'customermaster.channel');
            })
            ->whereIn('customermaster.customercode', $customerCodes->unique()->values())
            ->select('customermaster.customercode')
            ->selectRaw('CASE WHEN customermaster.customerfacetime > 0 THEN customermaster.customerfacetime
                WHEN cft_mapping.cft > 0 THEN cft_mapping.cft ELSE 0 END as planned_minutes')
            ->get()->mapWithKeys(fn ($customer) => [$customer->customercode => (int) $customer->planned_minutes]);
    }
}
