<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupervisorFreeContractDetail extends Model
{
    protected $table = 'supervisorfreegoodsdetail';
    protected $primaryKey = null;
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'contractid',
        'itemcode',
        'freequantity',
        'balanceqty',
        'originalqty',
        'created',
        'cdat',
        'modified',
        'mdat',
    ];
}
