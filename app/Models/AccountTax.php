<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountTax extends Model
{
    protected $table = 'tbltaxmaster';

    protected $primaryKey = 'taxcode';

    public $timestamps = false;

    protected $fillable = [
        'taxcode',
        'taxdescription',
        'arbtaxdescription',
        'taxtype',
        'taxpercentage',
        'taxbase',
        'created',
        'cdat',
        'modified',
        'mdat',
    ];

    public function getRouteKeyName(): string
    {
        return 'taxcode';
    }
}
