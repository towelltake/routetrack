<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CountryMaster extends Model
{
    protected $table = 'country';
    protected $primaryKey = 'countrycode';
    public $timestamps = false;

    protected $fillable = [
        'alternatecode', 'countryname', 'arbcountryname',
        'currencycode', 'cmpycode', 'pricechangevariance',
        'nationalsalesmanagercode', 'created', 'cdat', 'modified', 'mdat',
    ];
}
