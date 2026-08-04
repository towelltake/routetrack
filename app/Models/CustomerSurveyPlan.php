<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerSurveyPlan extends Model
{
    protected $table = 'customersurveyplan';

    protected $primaryKey = 'surveyplankey';

    public $timestamps = false;

    protected $fillable = [
        'surveysequencenumber',
        'surveymandatory',
        'surveydescription',
        'arbsurveydescription',
        'created',
        'cdat',
        'modified',
        'mdat',
        'remarks',
    ];

    public function getRouteKeyName(): string
    {
        return 'surveyplankey';
    }
}
