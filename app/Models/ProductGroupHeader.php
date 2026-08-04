<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductGroupHeader extends Model
{
    protected $table = 'productgroupheader';

    protected $primaryKey = 'groupnumber';

    public $timestamps = false;

    protected $fillable = [
        'groupdescription',
        'arbgroupdescription',
        'grouptype',
        'created',
        'cdat',
        'modified',
        'mdat',
        'alternatecode',
        'divison',
    ];

    public function getRouteKeyName(): string
    {
        return 'groupnumber';
    }
}
