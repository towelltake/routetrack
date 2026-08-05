<?php

namespace App\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;

class SfaUserProvider implements UserProvider
{
    public function __construct(private readonly string $model) {}

    public function retrieveById($identifier): ?Authenticatable
    {
        $model = new $this->model;

        return $model->newQuery()->where($model->getAuthIdentifierName(), $identifier)->first();
    }

    public function retrieveByToken($identifier, #[\SensitiveParameter] $token): ?Authenticatable
    {
        return null;
    }

    public function updateRememberToken(Authenticatable $user, #[\SensitiveParameter] $token): void {}

    public function retrieveByCredentials(#[\SensitiveParameter] array $credentials): ?Authenticatable
    {
        if (!isset($credentials['username'])) {
            return null;
        }

        $model = new $this->model;

        return $model->newQuery()->where('username', $credentials['username'])->first();
    }

    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        return isset($credentials['password'])
            && (string) $user->getAuthPassword() === (string) $credentials['password'];
    }

    public function rehashPasswordIfRequired(Authenticatable $user, #[\SensitiveParameter] array $credentials, bool $force = false): void {}
}
