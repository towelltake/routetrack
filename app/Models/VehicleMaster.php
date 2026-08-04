<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehicleMaster extends Model
{
    protected $table = 'vehiclemaster';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'code', 'vandescription', 'arbvandescription',
        'vehicleregistration', 'vanmodel', 'vantype',
        'companyid', 'statusflag',
    ];

    public function company()
    {
        return $this->belongsTo(CompanyMaster::class, 'companyid');
    }
}
