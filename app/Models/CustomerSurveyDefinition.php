<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerSurveyDefinition extends Model
{
    protected $table = 'customersurveydefinition';

    protected $primaryKey = 'surveydefkey';

    public $timestamps = false;

    protected $fillable = [
        'surveyindex',
        'lineindex',
        'surveyrectype',
        'surveyprompt',
        'arbsurveyprompt',
        'responselength',
        'responsedecimalpos',
        'lookuptype',
        'lookupindex',
        'retainvalue',
        'activestatus',
        'created',
        'cdat',
        'modified',
        'mdat',
    ];

    public function getRouteKeyName(): string
    {
        return 'surveydefkey';
    }
}
