<?php

namespace Xul\AuthKit\Tests\Support;

use Illuminate\Support\Str;
use Xul\AuthKit\Contracts\TokenRepositoryContract;

/**
 * ArrayTokenRepository
 *
 * In-memory token repository used for AuthKit tests.
 */
final class ArrayTokenRepository implements TokenRepositoryContract
{
    /**
     * @var array<string, array<string, array<string, mixed>>>
     */
    private array $store = [];

    public function create(string $type, string $identifier, int $ttlMinutes, array $payload = [], array $options = []): string
    {
        $token = $this->makeToken((array) $options);

        $this->store[$type][$identifier][$token] = [
            'payload' => $payload,
            'expires_at' => now()->addMinutes(max(1, $ttlMinutes))->getTimestamp(),
        ];

        return $token;
    }

    public function peek(string $type, string $identifier, string $token): ?array
    {
        $row = $this->store[$type][$identifier][$token] ?? null;

        if (!is_array($row)) {
            return null;
        }

        if ((int) ($row['expires_at'] ?? 0) < now()->getTimestamp()) {
            unset($this->store[$type][$identifier][$token]);

            return null;
        }

        $payload = $row['payload'] ?? null;

        return is_array($payload) ? $payload : null;
    }

    public function validate(string $type, string $identifier, string $token): ?array
    {
        $payload = $this->peek($type, $identifier, $token);

        if ($payload === null) {
            return null;
        }

        unset($this->store[$type][$identifier][$token]);

        return $payload;
    }

    public function delete(string $type, string $identifier, string $token): void
    {
        unset($this->store[$type][$identifier][$token]);
    }

    private function makeToken(array $options): string
    {
        $len = (int) ($options['length'] ?? 64);
        $alphabet = (string) ($options['alphabet'] ?? 'alnum');
        $uppercase = (bool) ($options['uppercase'] ?? false);

        $token = match ($alphabet) {
            'digits' => $this->digits($len),
            'alpha' => Str::lower(Str::random($len)),
            'hex' => bin2hex(random_bytes((int) ceil($len / 2))),
            default => Str::random($len),
        };

        $token = substr($token, 0, max(1, $len));

        return $uppercase ? strtoupper($token) : $token;
    }

    private function digits(int $len): string
    {
        $len = max(1, $len);
        $out = '';

        while (strlen($out) < $len) {
            $out .= (string) random_int(0, 9);
        }

        return substr($out, 0, $len);
    }
}