<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected ?array $cachedFormPermissions = null;

    protected $table = 'usermaster';
    protected $connection = 'sfa_mysql';
    protected $primaryKey = 'userid';
    public $timestamps = false;

    protected $fillable = [
        'username',
        'email',
        'password',
        'usertypeid',
        'accesstypeid',
    ];

    protected $hidden = [
        'password',
    ];

    protected $appends = ['name', 'gravatar'];

    // usermaster has no remember_token column
    public function getRememberToken()
    {
        return null;
    }

    public function setRememberToken($value) {}

    public function getRememberTokenName()
    {
        return '';
    }

    // Authenticated layout expects auth.user.name
    public function getNameAttribute(): string
    {
        return $this->username ?? '';
    }

    // Generate avatar from username since there is no email column
    public function getGravatarAttribute(): string
    {
        return 'https://ui-avatars.com/api/?name=' . urlencode($this->username) . '&size=128&background=4f46e5&color=ffffff&bold=true';
    }

    public function userType(): BelongsTo
    {
        return $this->belongsTo(UserType::class, 'usertypeid', 'usertypeid');
    }

    public function details(): HasMany
    {
        return $this->hasMany(UserDetail::class, 'userid', 'userid');
    }

    public function permissions()
    {
        return collect($this->formPermissions())
            ->filter(fn (array $permission) => $permission['all'] || $permission['view'] || $permission['read'])
            ->keys()
            ->values();
    }

    public function hasPermission(string $permissionCode): bool
    {
        $segments = explode('.', $permissionCode);
        $action = count($segments) > 1 ? array_pop($segments) : 'view';
        $formName = implode('.', $segments);

        if (!in_array($action, ['view', 'read', 'create', 'write', 'edit', 'delete', 'all'], true)) {
            $formName = $permissionCode;
            $action = 'view';
        }

        return $this->hasFormPermission($formName, $action);
    }

    public function formPermissions(): array
    {
        if ($this->cachedFormPermissions !== null) {
            return $this->cachedFormPermissions;
        }

        $hasUserPermissions = UserDetail::query()->where('userid', $this->userid)->exists();
        $rows = ($hasUserPermissions
            ? UserDetail::query()->from('userdetail as permission')->where('permission.userid', $this->userid)
            : UserTypeDetail::query()->from('usertypedetail as permission')->where('permission.usertypeid', $this->usertypeid))
            ->join('moduledetail as md', 'permission.formid', '=', 'md.formid')
            ->get([
                'md.formname',
                'permission.readdata',
                'permission.updatedata',
                'permission.insertdata',
                'permission.deletedata',
                'permission.allpermissions',
            ]);

        return $this->cachedFormPermissions = $rows->mapWithKeys(function ($detail) {
            $key = $this->normalizePermissionKey($detail->formname);
            $read = (bool) $detail->readdata;

            return [$key => [
                'view' => $read,
                'read' => $read,
                'create' => (bool) $detail->insertdata,
                'write' => (bool) $detail->updatedata,
                'delete' => (bool) $detail->deletedata,
                'all' => (bool) $detail->allpermissions,
            ]];
        })->all();
    }

    public function hasFormPermission(string $formName, string $action = 'view'): bool
    {
        $permission = $this->formPermissions()[$this->normalizePermissionKey($formName)] ?? null;

        if (!$permission) {
            return false;
        }

        if ($permission['all']) {
            return true;
        }

        return match ($action) {
            'view' => $permission['view'],
            'read' => $permission['read'],
            'create' => $permission['create'],
            'edit' => $permission['write'],
            'write' => $permission['write'],
            'delete' => $permission['delete'],
            'all' => $permission['all'],
            default => false,
        };
    }

    private function normalizePermissionKey(?string $value): string
    {
        $value = strtolower(trim((string) $value));
        $value = preg_replace('/[\s_-]+/', ' ', $value);

        return $value ?? '';
    }

}
