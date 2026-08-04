<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupervisorFocBalance extends Model
{
    protected $table = 'supervisor_foc_balance';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'contractid',
        'supervisorcode',
        'itemcode',
        'originalqty',
        'balanceqty',
        'startdate',
    ];
}
