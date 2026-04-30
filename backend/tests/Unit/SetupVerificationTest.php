<?php

use App\Models\User;
use Tymon\JWTAuth\Contracts\JWTSubject;

it('has User model implementing JWTSubject', function () {
    $user = new User();
    expect($user)->toBeInstanceOf(JWTSubject::class);
});

it('has User model with UUID key type', function () {
    $user = new User();
    expect($user->getKeyType())->toBe('string');
    expect($user->getIncrementing())->toBeFalse();
});
