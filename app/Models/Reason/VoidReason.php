<?php

namespace App\Models\Reason;

use Illuminate\Database\Eloquent\Model;

class VoidReason extends Model
{
    protected $table = 'voidreasons';
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
