<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryLocation extends Model
{
    protected $table = 'inventorylocation';
    protected $primaryKey = 'code';
    public $timestamps = false;

    protected $fillable = [
        'alternatecode',
        'description',
        'arbdescription',
        'hhcdescription',
        'created',
        'cdat',
        'modified',
        'mdat',
    ];
}
