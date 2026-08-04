<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CategoryMaster extends Model
{
    protected $table = 'categorymaster';
    protected $primaryKey = 'categoryid';
    public $timestamps = false;

    protected $fillable = [
        'alternatecode', 'categoryname', 'arbcategoryname', 'activestatus',
        'created', 'cdat', 'modified', 'mdat',
    ];
}
