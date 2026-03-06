<?php
// file: src/RateLimiting/Contracts/ChallengeResolverContract.php

namespace Xul\AuthKit\RateLimiting\Contracts;

use Illuminate\Http\Request;

interface ChallengeResolverContract
{
    /**
     * Resolve the challenge reference used for per-challenge throttling (primarily 2FA flows).
     *
     * Return null when challenge is not available so the challenge bucket can be skipped safely.
     */
    public function resolve(Request $request): ?string;
}