<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserType extends Model
{
    protected $table = 'usertype';
    protected $primaryKey = 'usertypeid';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = ['usertypeid', 'usertypename'];

    protected $appends = ['id', 'user_type'];

    public function getIdAttribute(): ?int
    {
        return $this->usertypeid;
    }

    public function getUserTypeAttribute(): ?string
    {
        return $this->usertypename;
    }

    public function users()
    {
        return $this->hasMany(User::class, 'usertypeid', 'usertypeid');
    }
}
