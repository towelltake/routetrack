<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesmanMessage extends Model
{
    protected $table = 'salesmanmessages';

    protected $primaryKey = 'messagekey';

    public $timestamps = false;

    protected $fillable = [
        'alternatecode',
        'messagedescription',
        'message1',
        'message2',
        'message3',
        'message4',
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
