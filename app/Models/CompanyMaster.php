<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyMaster extends Model
{
    protected $table = 'company';
    protected $primaryKey = 'cmpycode';
    public $timestamps = false;

    protected $fillable = [
        'alternatecmpycode', 'name', 'arbcompanyname', 'parentcompany',
        'contactname', 'address', 'telephone', 'fax', 'zipcode',
        'countrycode', 'countryname', 'taxregistrationnumber',
        'distributorcode', 'activestatus',
        'created', 'cdat', 'modified', 'mdat',
    ];

    public function parent()
    {
        return $this->belongsTo(CompanyMaster::class, 'parentcompany', 'cmpycode');
    }
}
