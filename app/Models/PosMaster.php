<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PosMaster extends Model
{
    protected $table = 'posmaster';

    protected $primaryKey = 'itemcode';

    public $timestamps = false;

    protected $fillable = [
        'alternatecode',
        'itemdescription',
        'arbitemdescription',
        'itemvalue',
        'inventorytype',
        'created',
        'cdat',
        'modified',
        'mdat',
        'activestatus',
    ];

    public function getRouteKeyName(): string
    {
        return 'itemcode';
    }
}
