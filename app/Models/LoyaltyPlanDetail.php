<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoyaltyPlanDetail extends Model
{
    protected $table = 'loyaltyplandetail';

    protected $primaryKey = 'primarykey';

    public $timestamps = false;

    protected $fillable = [
        'loyaltyplanid',
        'qualificationgroup',
        'type',
        'value',
        'points',
        'memo1',
        'created',
        'cdat',
        'modified',
        'mdat',
    ];
}
