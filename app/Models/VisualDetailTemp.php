<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VisualDetailTemp extends Model
{
    protected $table = 'visualdetail_temp';

    protected $primaryKey = 'visualdetail_id';

    public $timestamps = false;

    protected $fillable = [
        'visualcode',
        'imagename',
        'imagepath',
        'imagedescription',
    ];

    public function getRouteKeyName(): string
    {
        return 'visualdetail_id';
    }
}
