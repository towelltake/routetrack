<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductGroupDetail extends Model
{
    protected $table = 'productgroupdetail';

    protected $primaryKey = 'primary_key';

    public $timestamps = false;

    protected $fillable = [
        'groupnumber',
        'itemcode',
        'itemqty',
        'promopcprice',
        'promocaseprice',
        'modified',
        'divison',
    ];
}
