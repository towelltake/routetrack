<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RouteCategory extends Model
{
    protected $table = 'routecategory';
    protected $primaryKey = 'routecatcode';
    public $timestamps = false;

    protected $fillable = [
        'routecatname',
        'arbroutecatname',
        'created',
        'cdat',
        'modified',
        'mdat',
    ];
}
