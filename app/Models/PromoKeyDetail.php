<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromoKeyDetail extends Model
{
    protected $table = 'promokeydetail';
    protected $primaryKey = 'primary_key';
    public $timestamps = false;
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'plannumber',
        'promotionkey',
        'startdate',
        'enddate',
        'promotiontypecode',
        'qualificationgroup',
        'assignmentgroup',
        'assignmentnumber',
        'performcriteriakey',
        'rangebasis',
        'amountbasis',
        'exclusionoption',
        'active',
        'iscase',
        'created',
        'cdat',
        'modified',
        'mdat',
        'alternatecode',
        'divison',
        'memo1',
    ];
}
