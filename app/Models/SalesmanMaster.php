<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesmanMaster extends Model
{
    protected $table = 'salesmanmaster';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'code', 'salesmanname', 'arbsalesmanname',
        'contactnumber', 'companyid', 'username', 'userpassword',
        'statusflag',
    ];

    public function company()
    {
        return $this->belongsTo(CompanyMaster::class, 'companyid');
    }
}
