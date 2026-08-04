<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VanMaster extends Model
{
    protected $table = 'vanmaster';
    protected $primaryKey = 'vancode';
    public $timestamps = false;

    protected $fillable = [
        'alternatecode',
        'vanregno',
        'vanmodel',
        'vantype',
        'vandescription',
        'arbvandescription',
        'activestatus',
        'created',
        'cdat',
        'modified',
        'mdat',
    ];
}
