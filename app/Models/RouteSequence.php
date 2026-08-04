<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RouteSequence extends Model
{
    protected $table = 'routesequence';

    protected $primaryKey = 'primary_key';

    public $timestamps = false;

    protected $fillable = [
        'rp32weeknumber',
        'routecode',
        'customercode',
        'callrestrictiondays1',
        'callrestrictiondays2',
        'callrestrictiondays3',
        'callrestrictiondays4',
        'callrestrictiondays5',
        'callrestrictiondays6',
        'callrestrictiondays7',
        'monseq',
        'tueseq',
        'wedseq',
        'thuseq',
        'friseq',
        'satseq',
        'sunseq',
        'referenceno',
        'oldcustcode',
    ];
}
