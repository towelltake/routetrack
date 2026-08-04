<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerPricing1 extends Model
{
    protected $table = 'customerpricing1';
    protected $primaryKey = 'primary_key';
    public $timestamps = false;
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'pricingplankey',
        'customerpricingkey',
        'description',
        'startdate',
        'enddate',
        'arbdescription',
        'contractno',
        'active',
        'sequencecode',
        'created',
        'cdat',
        'modified',
        'mdat',
        'alternatecode',
        'division',
    ];
}
