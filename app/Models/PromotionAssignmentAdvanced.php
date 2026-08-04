<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromotionAssignmentAdvanced extends Model
{
    protected $table = 'promotionassignmentadvanced';
    protected $primaryKey = 'range_id';
    public $timestamps = false;
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'plannumber',
        'assignmentnumber',
        'rangelow',
        'rangehigh',
        'repeatingrange',
        'promotionamount',
        'created',
        'cdat',
        'modified',
        'mdat',
        'alternatecode',
        'divison',
    ];
}
