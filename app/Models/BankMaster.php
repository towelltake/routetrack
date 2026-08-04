<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankMaster extends Model
{
    protected $table = 'bankmaster';
    protected $primaryKey = 'bankcode';
    public $timestamps = false;

    protected $fillable = [
        'bankname', 'arbbankname', 'alternatecode',
        'type', 'acnumber', 'activestatus', 'bankbalance',
        'created', 'cdat', 'modified', 'mdat',
    ];
}
