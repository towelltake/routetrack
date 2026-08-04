<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VisualHeader extends Model
{
    protected $table = 'visualheader';

    protected $primaryKey = 'visualcode';

    public $timestamps = false;

    protected $fillable = [
        'visualdescription',
        'arbvisualdescription',
        'remarks',
    ];

    public function getRouteKeyName(): string
    {
        return 'visualcode';
    }
}
