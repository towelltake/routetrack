<?php

namespace App\Models;

use App\Notifications\TemplatePasswordResetNotification;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Schema;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected ?array $cachedFormPermissions = null;

    protected $table = 'usermaster';
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

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new TemplatePasswordResetNotification($token));
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

        $permissions = [];

        // Step 1: user-type permissions as baseline
        if (!empty($this->usertypeid)) {
            $this->mergePermissions(
                $permissions,
                UserTypeDetail::query()
                    ->where('usertypeid', $this->usertypeid)
                    ->get($this->permissionColumns('usertypedetail'))
            );
        }

        // Step 2: per-user rows completely override the type baseline for that form.
        // This lets an admin restrict a specific user below what their type allows.
        foreach ($this->details()->get($this->permissionColumns('userdetail')) as $detail) {
            $key = $this->normalizePermissionKey($detail->formname);

            if ($key === '') {
                continue;
            }

            $permissions[$key] = [
                'view'   => (bool) ($detail->viewdata ?? 0),
                'read'   => (bool) $detail->readdata,
                'create' => (bool) $detail->insertdata,
                'write'  => (bool) $detail->updatedata,
                'delete' => (bool) $detail->deletedata,
                'all'    => (bool) $detail->allpermissions,
            ];
        }

        return $this->cachedFormPermissions = $permissions;
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

    private function permissionColumns(string $table): array
    {
        $columns = ['formname', 'readdata', 'updatedata', 'insertdata', 'deletedata', 'allpermissions'];

        if (Schema::hasColumn($table, 'viewdata')) {
            array_splice($columns, 1, 0, 'viewdata');
        }

        return $columns;
    }

    private function mergePermissions(array &$permissions, iterable $rows): void
    {
        foreach ($rows as $detail) {
            $key = $this->normalizePermissionKey($detail->formname);

            if ($key === '') {
                continue;
            }

            $existing = $permissions[$key] ?? [
                'view' => false,
                'read' => false,
                'create' => false,
                'write' => false,
                'delete' => false,
                'all' => false,
            ];

            $permissions[$key] = [
                'view' => $existing['view'] || (bool) ($detail->viewdata ?? 0),
                'read' => $existing['read'] || (bool) $detail->readdata,
                'create' => $existing['create'] || (bool) $detail->insertdata,
                'write' => $existing['write'] || (bool) $detail->updatedata,
                'delete' => $existing['delete'] || (bool) $detail->deletedata,
                'all' => $existing['all'] || (bool) $detail->allpermissions,
            ];
        }
    }
}
