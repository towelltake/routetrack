<?php

namespace App\Models\Reason;

use Illuminate\Database\Eloquent\Model;

class BadReturnReason extends Model
{
    protected $table = 'expiryreturnreasons';
    protected $primaryKey = 'code';
    public $timestamps = false;

    protected $fillable = [
        'alternatecode',
        'description',
        'arbdescription',
        'hhcdescription',
        'created',
        'cdat',
        'modified',
        'mdat',
    ];
}
