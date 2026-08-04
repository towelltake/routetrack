<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RegionMaster extends Model
{
    protected $table = 'regionmaster';
    protected $primaryKey = 'regionmstcode';
    public $timestamps = false;

    protected $fillable = [
        'alternatecode', 'regionmstname', 'arbregionmstname',
        'countrycode', 'regionmanagercode', 'created', 'cdat', 'modified', 'mdat',
    ];
}
