<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerSurveyKey extends Model
{
    protected $table = 'customersurveykey';

    protected $primaryKey = 'surveykey';

    public $timestamps = false;

    protected $fillable = [
        'surveydescription',
        'arbsurveydescription',
        'surveyplankey',
        'created',
        'cdat',
        'modified',
        'mdat',
        'activestatus',
    ];

    public function getRouteKeyName(): string
    {
        return 'surveykey';
    }
}
