<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupervisorFoc extends Model
{
    protected $table = 'supervisor_foc';
    protected $primaryKey = 'contractid';
    public $timestamps = false;

    protected $fillable = [
        'supervisorcode',
        'creationdate',
        'remarks',
        'startdate',
        'enddate',
        'active',
        'depotcode',
    ];
}
