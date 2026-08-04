<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PricingDetail1 extends Model
{
    protected $table = 'pricingdetail1';
    protected $primaryKey = 'primary_key';
    public $timestamps = false;
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'customerpricingkey',
        'itemcode',
        'salesprice',
        'returnprice',
        'retailprice',
        'salescaseprice',
        'returncaseprice',
        'unitspercase',
        'stdsalesunitprice',
        'stdreturnunitprice',
        'stdsalescaseprice',
        'stdreturncaseprice',
        'created',
        'cdat',
        'modified',
        'mdat',
        'alternatecode',
    ];
}
