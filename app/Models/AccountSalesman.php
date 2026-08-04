<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountSalesman extends Model
{
    protected $table = 'salesman';

    protected $primaryKey = 'salesmancode';

    public $timestamps = false;

    protected $fillable = [
        'alternatesalesmancode',
        'salesmanname1',
        'salesmanname2',
        'arbsalesmanname1',
        'messagekey',
        'pricingkey',
        'created',
        'cdat',
        'modified',
        'mdat',
        'memo1',
        'memo2',
        'type',
        'activestatus',
        'parentcompany',
        'ansalesmancode',
        'username',
        'userpassword',
    ];

    protected $casts = [
        'salesmancode' => 'integer',
        'messagekey' => 'integer',
        'type' => 'integer',
        'activestatus' => 'integer',
        'parentcompany' => 'integer',
        'cdat' => 'datetime',
        'mdat' => 'datetime',
    ];
}
