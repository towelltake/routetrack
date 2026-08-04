<?php

namespace App\Models\Reason;

use Illuminate\Database\Eloquent\Model;

class GoodReturnReason extends Model
{
    protected $table = 'retitmreasons';
    protected $primaryKey = 'code';
    public $timestamps = false;

    protected $fillable = [
        'alternatecode',
        'description',
        'arbdescription',
        'hhcdescription',
        'activestatus',
        'created',
        'cdat',
        'modified',
        'mdat',
    ];
}
