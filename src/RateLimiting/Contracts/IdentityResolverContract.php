<?php
// file: src/RateLimiting/Contracts/IdentityResolverContract.php

namespace Xul\AuthKit\RateLimiting\Contracts;

use Illuminate\Http\Request;

interface IdentityResolverContract
{
    /**
     * Resolve the normalized identity used for per-identity throttling (e.g. email/username/phone).
     *
     * Return null when identity is not available so the identity bucket can be skipped safely.
     */
    public function resolve(Request $request): ?string;
}