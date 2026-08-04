<?php

namespace App\Models\Reason;

use Illuminate\Database\Eloquent\Model;

class ExpenseReason extends Model
{
    protected $table = 'expreasons';
    protected $primaryKey = 'code';
    public $timestamps = false;

    protected $fillable = [
        'alternatecode',
        'description',
        'arbdescription',
        'hhcdescription',
        'allowliterentry',
        'created',
        'cdat',
        'modified',
        'mdat',
    ];
}
