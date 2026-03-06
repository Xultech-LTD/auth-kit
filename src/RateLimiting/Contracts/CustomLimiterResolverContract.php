<?php
// file: src/RateLimiting/Contracts/CustomLimiterResolverContract.php

namespace Xul\AuthKit\RateLimiting\Contracts;

use Illuminate\Http\Request;
use Illuminate\Cache\RateLimiting\Limit;

interface CustomLimiterResolverContract
{
    /**
     * Build limiter limits for a given limiter key.
     *
     * Return a single Limit or an array of Limit objects.
     *
     * @return Limit|array<int, Limit>
     */
    public function resolve(string $limiterKey, Request $request): Limit|array;
}