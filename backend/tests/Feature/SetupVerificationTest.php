<?php

/**
 * These tests verify the application configuration is correctly set up.
 * Note: phpunit.xml overrides some values for the test environment
 * (e.g., DB_CONNECTION=sqlite, CACHE_STORE=array, QUEUE_CONNECTION=sync).
 * We test the actual config structure rather than runtime env values.
 */

it('has pgsql database connection configured', function () {
    // The pgsql connection should be properly configured in database.php
    $pgsqlConfig = config('database.connections.pgsql');
    expect($pgsqlConfig)->not->toBeNull();
    expect($pgsqlConfig['driver'])->toBe('pgsql');
    expect($pgsqlConfig['port'])->toBe('5432');
});

it('has redis cache store configured', function () {
    $redisStore = config('cache.stores.redis');
    expect($redisStore)->not->toBeNull();
    expect($redisStore['driver'])->toBe('redis');
});

it('has redis queue connection configured', function () {
    $redisQueue = config('queue.connections.redis');
    expect($redisQueue)->not->toBeNull();
    expect($redisQueue['driver'])->toBe('redis');
});

it('has redis database configuration', function () {
    $redisConfig = config('database.redis');
    expect($redisConfig)->not->toBeNull();
    expect($redisConfig['default'])->toBeArray();
    expect($redisConfig['cache'])->toBeArray();
});

it('has correct JWT configuration', function () {
    expect(config('jwt.ttl'))->toBe('15');
    expect(config('jwt.algo'))->toBe('HS256');
    expect(config('jwt.blacklist_enabled'))->toBeTrue();
    expect(config('jwt.secret'))->not->toBeNull();
});

it('has JWT API guard configured', function () {
    expect(config('auth.guards.api.driver'))->toBe('jwt');
    expect(config('auth.guards.api.provider'))->toBe('users');
});

it('has required JWT claims configured', function () {
    $requiredClaims = config('jwt.required_claims');
    expect($requiredClaims)->toContain('iss');
    expect($requiredClaims)->toContain('iat');
    expect($requiredClaims)->toContain('exp');
    expect($requiredClaims)->toContain('sub');
    expect($requiredClaims)->toContain('jti');
});
