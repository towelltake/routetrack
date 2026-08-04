<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CurrencyMaster extends Model
{
    protected $table = 'currencymaster';
    protected $primaryKey = 'currencycode';
    public $timestamps = false;

    protected $fillable = [
        'alternatecode', 'currencyname', 'arbcurrencyname',
        'currencysymbol', 'arbcurrencysymbol', 'decimalplaces',
        'defaultcurrency', 'startdate', 'enddate',
    ];
}
