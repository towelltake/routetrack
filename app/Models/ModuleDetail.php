<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModuleDetail extends Model
{
    protected $table = 'moduledetail';
    protected $primaryKey = 'formid';
    public $timestamps = false;

    protected $fillable = ['formname', 'formdescription', 'moduleid', 'order'];

    public function module()
    {
        return $this->belongsTo(ModuleHeader::class, 'moduleid', 'moduleid');
    }
}
