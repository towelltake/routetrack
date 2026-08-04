<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoyaltyKeyDetail extends Model
{
    protected $table = 'loyaltykeydetail';

    protected $primaryKey = 'primarykey';

    public $timestamps = false;

    protected $fillable = [
        'loyaltykeyid',
        'loyaltyplanid',
        'startdate',
        'enddate',
        'memo1',
    ];
}
