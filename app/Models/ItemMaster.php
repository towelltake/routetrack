<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemMaster extends Model
{
    protected $table = 'itemmaster';

    protected $primaryKey = 'actualitemcode';

    public $timestamps = false;
}
