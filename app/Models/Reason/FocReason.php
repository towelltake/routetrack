<?php

namespace App\Models\Reason;

use Illuminate\Database\Eloquent\Model;

class FocReason extends Model
{
    protected $table = 'freegoodreasons';
    protected $primaryKey = 'reason_code';
    public $timestamps = false;

    protected $fillable = [
        'alternatereasoncode',
        'reason_desc',
        'reason_arb_desc',
        'created',
        'cdat',
        'modified',
        'mdat',
    ];
}
