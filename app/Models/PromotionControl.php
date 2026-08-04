<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromotionControl extends Model
{
    protected $table = 'promotioncontrol';
    protected $primaryKey = 'promotionplannumber';
    public $timestamps = false;

    protected $fillable = [
        'promotionplannumber',
        'promotionkey',
        'startdate',
        'enddate',
        'promotiondescription',
        'arbpromotiondescription',
        'promotiontypecode',
        'qualificationgroup',
        'assignmentgroup',
        'assignmentnumber',
        'performcriteriakey',
        'rangebasis',
        'amountbasis',
        'exclusionoption',
        'excludebalanceexists',
        'paymenttypecontrol',
        'inventorycaseunit',
        'iscase',
        'rentindicator',
        'cashonlypromo',
        'onetimeuse',
        'status',
        'enforcepromotion',
    ];
}
