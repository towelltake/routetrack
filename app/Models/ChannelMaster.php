<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChannelMaster extends Model
{
    protected $table = 'channelmaster';
    protected $primaryKey = 'channelcode';
    public $timestamps = false;

    protected $fillable = [
        'alternatecode', 'channelname', 'arbchannelname', 'activestatus',
        'created', 'cdat', 'modified', 'mdat',
    ];
}
