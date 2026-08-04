<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubAreaMaster extends Model
{
    protected $table = 'subareamaster';
    protected $primaryKey = 'subareacode';
    public $timestamps = false;

    protected $fillable = [
        'subareaname', 'arbsubareaname', 'alternatesubareacode',
        'areacode', 'supervisorcode', 'activestatus',
        'created', 'cdat', 'modified', 'mdat',
    ];

    public function area()
    {
        return $this->belongsTo(AreaMaster::class, 'areacode', 'areacode');
    }
}
