<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoyaltyPlanHeader extends Model
{
    protected $table = 'loyaltyplanheader';

    protected $primaryKey = 'loyaltyplanid';

    public $timestamps = false;

    protected $fillable = [
        'description',
        'arbdescription',
        'active',
        'remarks',
        'created',
        'cdat',
        'modified',
        'mdat',
    ];

    public function getRouteKeyName(): string
    {
        return 'loyaltyplanid';
    }
}
