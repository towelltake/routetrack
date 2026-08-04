<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserAccessCode extends Model
{
    protected $table = 'useraccesscodes';
    public $timestamps = false;
    public $incrementing = false;
    protected $primaryKey = null;

    protected $fillable = [
        'username',
        'cmpycode',
        'countrycode',
        'regionmstcode',
        'depotcode',
        'areacode',
        'subareacode',
    ];
}
