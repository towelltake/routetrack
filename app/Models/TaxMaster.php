<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaxMaster extends Model
{
    protected $table = 'taxmaster';
    protected $primaryKey = 'taxcode';
    public $incrementing = false;
    public $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'taxcode', 'taxdescription', 'arbtaxdescription', 'pricecomponent',
    ];
}
