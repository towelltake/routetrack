<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DepotMaster extends Model
{
    protected $table = 'depotmaster';
    protected $primaryKey = 'depotcode';
    public $timestamps = false;

    protected $fillable = [
        'alternatedepotcode', 'depotname', 'arbdepotname',
        'cmpycode', 'branchmanagercode', 'regionmstcode', 'pricingkey',
        'depotprefix', 'centralwh', 'activestatus',
        'phonenumber', 'faxnumber',
        'created', 'cdat', 'modified', 'mdat',
    ];

    public function company()
    {
        return $this->belongsTo(CompanyMaster::class, 'cmpycode', 'cmpycode');
    }

    public function region()
    {
        return $this->belongsTo(RegionMaster::class, 'regionmstcode', 'regionmstcode');
    }
}
