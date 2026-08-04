<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PosInstruction extends Model
{
    protected $table = 'posinstructions';

    protected $primaryKey = 'posinstructioncode';

    public $timestamps = false;

    protected $casts = [
        'cdat' => 'datetime',
        'mdat' => 'datetime',
    ];

    protected $fillable = [
        'alternatecode',
        'posinstructionname',
        'arbposinstructionname',
        'created',
        'cdat',
        'modified',
        'mdat',
    ];

    public function getRouteKeyName(): string
    {
        return 'posinstructioncode';
    }
}
