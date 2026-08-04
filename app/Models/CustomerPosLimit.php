<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerPosLimit extends Model
{
    protected $table = 'customerposlimit';

    protected $primaryKey = 'primary_key';

    public $timestamps = false;

    protected $fillable = [
        'customercode',
        'poslimit',
        'posbalance',
        'created',
        'cdat',
        'modified',
        'mdat',
    ];

    public function getRouteKeyName(): string
    {
        return 'primary_key';
    }
}
