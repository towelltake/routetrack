<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReasonMaster extends Model
{
    protected $table = 'reasonmaster';
    public $timestamps = false;

    protected $fillable = [
        'code', 'description', 'arbdescription', 'alternatecode', 'type',
    ];

    public static array $types = [
        'badreturn'  => 'Bad Return',
        'goodreturn' => 'Good Return',
        'foc'        => 'FOC / Free Goods',
        'expense'    => 'Expense',
        'nonservice' => 'Non Service',
        'void'       => 'Void',
    ];
}
