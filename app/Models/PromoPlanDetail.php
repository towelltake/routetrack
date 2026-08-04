<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromoPlanDetail extends Model
{
    protected $table = 'promoplandetail';
    protected $primaryKey = 'plannumber';
    public $timestamps = false;
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'plannumber',
        'qualificationgroup',
        'assignmentgroup',
        'performcriteriakey',
        'rangebasis',
        'amountbasis',
        'exclusionoption',
        'assignmentnumber',
        'plandescription',
        'arbplandescription',
        'promotiontypecode',
        'rentindicator',
        'iscase',
        'onetimeuse',
        'enforcepromotion',
        'repeatrange',
        'created',
        'cdat',
        'modified',
        'mdat',
        'alternatecode',
        'memo1',
        'divison',
    ];
}
