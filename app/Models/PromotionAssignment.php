<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromotionAssignment extends Model
{
    protected $table = 'promotionassignment';
    protected $primaryKey = null;
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'assignmentnumber',
        'rangelow',
        'rangehigh',
        'repeatingrange',
        'promotionamount',
    ];
}
