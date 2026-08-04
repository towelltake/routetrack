<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceMaster extends Model
{
    protected $table = 'devicemaster';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'deviceid', 'remarks', 'companyid', 'statusflag',
    ];

    public function company()
    {
        return $this->belongsTo(CompanyMaster::class, 'companyid');
    }
}
