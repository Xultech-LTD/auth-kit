<?php
// file: src/RateLimiting/Contracts/IpResolverContract.php

namespace Xul\AuthKit\RateLimiting\Contracts;

use Illuminate\Http\Request;

interface IpResolverContract
{
    /**
     * Resolve a stable client IP identifier for throttling.
     *
     * MUST return a non-empty string.
     */
    public function resolve(Request $request): string;
}