<?php

namespace App\Models\Reason;

use Illuminate\Database\Eloquent\Model;

class NonServiceReason extends Model
{
    protected $table = 'nonservreasons';
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
