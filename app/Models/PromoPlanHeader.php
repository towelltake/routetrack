<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromoPlanHeader extends Model
{
    protected $table = 'promoplanheader';
    protected $primaryKey = 'plannumber';
    public $timestamps = false;
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'plannumber',
        'plandescription',
        'arbplandescription',
        'plantypecode',
        'activeindicator',
        'created',
        'cdat',
        'modified',
        'mdat',
        'alternatecode',
        'divison',
    ];
}
