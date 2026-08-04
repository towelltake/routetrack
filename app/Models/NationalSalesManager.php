<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NationalSalesManager extends Model
{
    protected $table = 'nationalsalesmanager';
    protected $primaryKey = 'nationalsalesmanagercode';
    public $timestamps = false;

    protected $fillable = [
        'alternatecode',
        'parentcompany',
        'nationalsalesmanagername',
        'arbnationalsalesmanagername',
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
