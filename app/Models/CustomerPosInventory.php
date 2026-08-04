<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerPosInventory extends Model
{
    protected $table = 'customerposinventory';

    protected $primaryKey = 'table_pk';

    public $timestamps = false;

    protected $fillable = [
        'customercode',
        'itemcode',
        'quantity',
        'serialnumber',
        'created',
        'cdat',
        'modified',
        'mdat',
        'installeddate',
    ];

    public function getRouteKeyName(): string
    {
        return 'table_pk';
    }
}
