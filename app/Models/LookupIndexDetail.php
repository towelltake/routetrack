<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LookupIndexDetail extends Model
{
    protected $table = 'lookupindexdetail';

    protected $primaryKey = 'primary_key';

    public $timestamps = false;

    protected $fillable = [
        'transactionkey',
        'description',
        'arbdescription',
        'created',
        'cdat',
        'modified',
        'mdat',
    ];

    public function getRouteKeyName(): string
    {
        return 'primary_key';
    }
}
