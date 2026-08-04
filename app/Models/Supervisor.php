<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Supervisor extends Model
{
    protected $table = 'supervisor';
    protected $primaryKey = 'supervisorcode';
    public $timestamps = false;

    protected $fillable = [
        'parentcompany',
        'supervisorname',
        'arbsupervisorname',
        'alternatesupervisorcode',
        'type',
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
