<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserTypeDetail extends Model
{
    protected $table = 'usertypedetail';
    protected $primaryKey = 'primary_key';
    public $timestamps = false;

    protected $fillable = [
        'usertypeid', 'formname', 'formdescription',
        'viewdata', 'readdata', 'updatedata', 'insertdata', 'deletedata',
        'allpermissions', 'moduleid', 'formid',
    ];
}
