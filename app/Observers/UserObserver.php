<?php

namespace App\Observers;

use App\Models\User;
use App\Support\UserPermissionSync;

class UserObserver
{
    public function created(User $user): void
    {
        UserPermissionSync::syncForUser($user);
    }

    public function updated(User $user): void
    {
        if ($user->wasChanged('usertypeid')) {
            UserPermissionSync::syncForUser($user);
            return;
        }

        if ($user->wasChanged('username')) {
            UserPermissionSync::syncUsername($user);
        }
    }

    public function deleted(User $user): void
    {
        UserPermissionSync::deleteForUser($user);
    }
}
