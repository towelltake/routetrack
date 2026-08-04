<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    protected $table = 'email_templates';

    protected $fillable = [
        'purpose',
        'name',
        'subject_en',
        'subject_ar',
        'body_en',
        'body_ar',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
