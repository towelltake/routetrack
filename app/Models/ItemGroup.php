<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemGroup extends Model
{
    protected $table = 'itemgroup';

    protected $primaryKey = 'itemgroupcode';

    public $timestamps = false;
}
