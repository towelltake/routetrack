<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModuleHeader extends Model
{
    protected $table = 'moduleheader';
    protected $primaryKey = 'moduleid';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = ['moduleid', 'modulename'];

    public function details()
    {
        return $this->hasMany(ModuleDetail::class, 'moduleid', 'moduleid');
    }
}
