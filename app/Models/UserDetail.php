<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserDetail extends Model
{
    protected $table = 'userdetail';
    public $primaryKey = null;
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'username', 'formname', 'formdescription',
        'viewdata', 'readdata', 'updatedata', 'insertdata', 'deletedata',
        'allpermissions', 'userid', 'moduleid', 'formid',
    ];
}
