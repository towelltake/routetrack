<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromoKeyHeader extends Model
{
    protected $table = 'promokeyheader';
    protected $primaryKey = 'promotionkey';
    public $timestamps = false;
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'promotionkey',
        'description',
        'arbdescription',
        'activeindicator',
        'type',
        'created',
        'cdat',
        'modified',
        'mdat',
        'alternatecode',
    ];
}
