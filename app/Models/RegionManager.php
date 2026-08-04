<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RegionManager extends Model
{
    protected $table = 'regionmanager';
    protected $primaryKey = 'regionmanagercode';
    public $timestamps = false;

    protected $fillable = [
        'alternatecode',
        'parentcompany',
        'regionmanagername',
        'arbregionmanagername',
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
