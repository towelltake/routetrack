<?php

use App\Auth\SfaUserProvider;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

test('SFA credentials are compared without modifying the source password', function () {
    $user = Mockery::mock(Authenticatable::class);
    $user->shouldReceive('getAuthPassword')->twice()->andReturn('legacy-password');

    $provider = new SfaUserProvider(User::class);

    expect($provider->validateCredentials($user, ['password' => 'legacy-password']))->toBeTrue()
        ->and($provider->validateCredentials($user, ['password' => 'wrong']))->toBeFalse();
});
