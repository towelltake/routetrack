<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoyaltyKeyHeader extends Model
{
    protected $table = 'loyaltykeyheader';

    protected $primaryKey = 'loyaltykeyid';

    public $timestamps = false;

    protected $fillable = [
        'description',
        'arabicdescription',
        'active',
        'remarks',
        'created',
        'cdat',
        'modified',
        'mdat',
    ];

    public function getRouteKeyName(): string
    {
        return 'loyaltykeyid';
    }
}
