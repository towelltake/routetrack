<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupervisorFreeContractHeader extends Model
{
    protected $table = 'supervisorfreegoods';
    protected $primaryKey = 'contractid';
    public $timestamps = false;

    protected $fillable = [
        'supervisorcode',
        'startdate',
        'enddate',
        'active',
        'depotcode',
        'remarks',
        'created',
        'cdat',
        'modified',
        'mdat',
    ];
}
