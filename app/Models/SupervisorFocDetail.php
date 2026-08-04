<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupervisorFocDetail extends Model
{
    protected $table = 'supervisor_foc_detail';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'contractid',
        'supervisorcode',
        'itemcode',
        'freequantity',
        'remarks',
        'editdate',
    ];
}
