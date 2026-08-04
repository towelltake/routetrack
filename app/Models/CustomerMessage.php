<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerMessage extends Model
{
    protected $table = 'customermessages';

    protected $primaryKey = 'messagekey';

    public $timestamps = false;

    protected $fillable = [
        'alternatecode',
        'messagedescription',
        'messageline1',
        'messageline2',
        'messageline3',
        'messageline4',
        'arbmessageline1',
        'arbmessageline2',
        'arbmessageline3',
        'arbmessageline4',
        'created',
        'cdat',
        'modified',
        'mdat',
        'activestatus',
    ];

    protected $casts = [
        'messagekey' => 'integer',
        'activestatus' => 'integer',
        'cdat' => 'datetime',
        'mdat' => 'datetime',
    ];
}
