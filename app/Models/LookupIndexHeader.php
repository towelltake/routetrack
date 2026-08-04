<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LookupIndexHeader extends Model
{
    protected $table = 'lookupindexheader';

    protected $primaryKey = 'transactionkey';

    public $timestamps = false;

    protected $fillable = [
        'description',
        'arbdescription',
        'response',
        'created',
        'cdat',
        'modified',
        'mdat',
    ];

    public function getRouteKeyName(): string
    {
        return 'transactionkey';
    }
}
