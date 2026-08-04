<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AreaMaster extends Model
{
    protected $table = 'areamaster';
    protected $primaryKey = 'areacode';
    public $timestamps = false;

    protected $fillable = [
        'areaname', 'arbareaname', 'alternateareacode',
        'depotcode', 'areamanagercode', 'areaprefix', 'activestatus',
        'created', 'cdat', 'modified', 'mdat',
    ];

    public function depot()
    {
        return $this->belongsTo(DepotMaster::class, 'depotcode', 'depotcode');
    }
}
