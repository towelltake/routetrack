<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BranchManager extends Model
{
    protected $table = 'branchmanager';
    protected $primaryKey = 'branchmanagercode';
    public $timestamps = false;

    protected $fillable = [
        'parentcompany',
        'branchmanagername',
        'arbbranchmanagername',
        'alternatebranchmanagercode',
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
