<?php

namespace App\Auth;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Hash;

class MigratingUserProvider extends EloquentUserProvider
{
    /**
     * Validates credentials and auto-upgrades plain-text passwords to bcrypt.
     * Handles both legacy plain-text passwords (old Zend project) and new bcrypt passwords.
     */
    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        $plain = $credentials['password'];
        $stored = $user->getAuthPassword();

        // Already bcrypt — standard check
        if (Hash::needsRehash($stored) === false && str_starts_with($stored, '$2')) {
            return Hash::check($plain, $stored);
        }

        // Plain-text password from old system — compare directly then upgrade
        if ($plain === $stored) {
            // Silently upgrade to bcrypt so next login uses Hash::check
            $user->forceFill(['password' => Hash::make($plain)])->save();
            return true;
        }

        // Try bcrypt check as a final fallback
        return Hash::check($plain, $stored);
    }
}
