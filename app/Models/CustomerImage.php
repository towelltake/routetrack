<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerImage extends Model
{
    protected $table = 'customerimages';

    protected $primaryKey = 'table_id';

    public $timestamps = false;

    protected $fillable = [
        'imagename',
        'customercode',
        'imageno',
        'imagepath',
        'routecode',
        'routekey',
        'transactiondate',
        'transactiontime',
        'visitkey',
        'remarks',
    ];

    protected $casts = [
        'transactiondate' => 'datetime',
        'transactiontime' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'table_id';
    }
}
