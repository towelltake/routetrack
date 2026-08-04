<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AreaManager extends Model
{
    protected $table = 'areamanager';
    protected $primaryKey = 'areamanagercode';
    public $timestamps = false;

    protected $fillable = [
        'parentcompany',
        'areamanagername',
        'arbareamanagername',
        'alternateareamanagercode',
        'created',
        'cdat',
        'modified',
        'mdat',
        'activestatus',
    ];

    public function company()
    {
        return $this->belongsTo(CompanyMaster::class, 'parentcompany', 'cmpycode');
    }
}
