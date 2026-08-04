<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerPricingPlanHeader1 extends Model
{
    protected $table = 'customerpricingplanheader1';
    protected $primaryKey = 'pricingplankey';
    public $timestamps = false;
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'pricingplankey',
        'description',
        'arbdescription',
        'activeindicator',
        'alternatecode',
    ];
}
