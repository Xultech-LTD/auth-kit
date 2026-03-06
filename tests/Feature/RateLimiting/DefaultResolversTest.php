<?php
// file: tests/Feature/RateLimiting/DefaultResolversTest.php

namespace Xul\AuthKit\Tests\Feature\RateLimiting;

use Illuminate\Http\Request;
use Xul\AuthKit\RateLimiting\DefaultChallengeResolver;
use Xul\AuthKit\RateLimiting\DefaultIdentityResolver;
use Xul\AuthKit\RateLimiting\DefaultIpResolver;

it('default ip resolver returns unknown when request ip is empty', function (): void {
    $resolver = new DefaultIpResolver();

    $req = Request::create('/x', 'GET');
    $req->server->set('REMOTE_ADDR', '');

    expect($resolver->resolve($req))->toBe('unknown');
});

it('default identity resolver reads configured field and applies lower normalization', function (): void {
    config()->set('authkit.identity.login.field', 'email');
    config()->set('authkit.identity.login.normalize', 'lower');

    $resolver = new DefaultIdentityResolver();

    $req = Request::create('/login', 'POST', ['email' => '  USER@EXAMPLE.COM  ']);

    expect($resolver->resolve($req))->toBe('user@example.com');
});

it('default identity resolver returns null for non-scalar values', function (): void {
    config()->set('authkit.identity.login.field', 'email');

    $resolver = new DefaultIdentityResolver();

    $req = Request::create('/login', 'POST', ['email' => ['x']]);

    expect($resolver->resolve($req))->toBeNull();
});

it('default challenge resolver resolves challenge input when scalar', function (): void {
    $resolver = new DefaultChallengeResolver();

    $req = Request::create('/two-factor/challenge', 'POST', ['challenge' => '  abc123  ']);

    expect($resolver->resolve($req))->toBe('abc123');
});

it('default challenge resolver returns null for empty or non-scalar', function (): void {
    $resolver = new DefaultChallengeResolver();

    $req1 = Request::create('/two-factor/challenge', 'POST', ['challenge' => '   ']);
    expect($resolver->resolve($req1))->toBeNull();

    $req2 = Request::create('/two-factor/challenge', 'POST', ['challenge' => ['x']]);
    expect($resolver->resolve($req2))->toBeNull();
});