<?php

namespace Xul\AuthKit\Support\App;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SessionViewDataResolver
 *
 * Resolves normalized session view data for the authenticated sessions page.
 *
 * Responsibilities:
 * - Determine whether database-backed session tracking is available.
 * - Read authenticated user sessions when possible.
 * - Normalize raw database session rows for Blade consumption.
 */
final class SessionViewDataResolver
{
    public function __construct(
        private readonly SessionBrowserInspector $inspector,
    ) {
    }

    /**
     * Resolve the sessions page view payload.
     *
     * @return array{
     *     sessions: \Illuminate\Support\Collection<int, array<string, mixed>>,
     *     supportsSessionTracking: bool
     * }
     */
    public function resolve(Request $request): array
    {
        $supportsSessionTracking = $this->supportsSessionTracking();

        if (! $supportsSessionTracking) {
            return [
                'sessions' => collect(),
                'supportsSessionTracking' => false,
            ];
        }

        $guard = (string) config('authkit.auth.guard', 'web');
        $user = auth($guard)->user();

        if ($user === null) {
            return [
                'sessions' => collect(),
                'supportsSessionTracking' => true,
            ];
        }

        return [
            'sessions' => $this->resolveUserSessions(
                userId: $user->getAuthIdentifier(),
                currentSessionId: (string) $request->session()->getId(),
            ),
            'supportsSessionTracking' => true,
        ];
    }

    /**
     * Determine whether the sessions table supports the required columns.
     */
    public function supportsSessionTracking(): bool
    {
        return Schema::hasTable('sessions')
            && Schema::hasColumns('sessions', [
                'id',
                'user_id',
                'ip_address',
                'user_agent',
                'last_activity',
            ]);
    }

    /**
     * Resolve normalized authenticated sessions for a user.
     *
     * @param mixed $userId
     * @return Collection<int, array<string, mixed>>
     */
    private function resolveUserSessions(mixed $userId, string $currentSessionId): Collection
    {
        return DB::table('sessions')
            ->where('user_id', $userId)
            ->orderByDesc('last_activity')
            ->get()
            ->map(function ($session) use ($currentSessionId): array {
                $userAgent = (string) ($session->user_agent ?? '');
                $ipAddress = (string) ($session->ip_address ?? 'Unknown');
                $lastActivityTimestamp = (int) ($session->last_activity ?? 0);

                $lastActivity = $lastActivityTimestamp > 0
                    ? Carbon::createFromTimestamp($lastActivityTimestamp)
                    : null;

                return [
                    'id' => (string) ($session->id ?? ''),
                    'ip_address' => $ipAddress !== '' ? $ipAddress : 'Unknown',
                    'user_agent' => $userAgent,
                    'device' => $this->inspector->resolveDeviceLabel($userAgent),
                    'browser' => $this->inspector->resolveBrowserLabel($userAgent),
                    'platform' => $this->inspector->resolvePlatformLabel($userAgent),
                    'is_current' => (string) ($session->id ?? '') === $currentSessionId,
                    'last_activity_at' => $lastActivity,
                    'last_activity_human' => $lastActivity?->diffForHumans(),
                ];
            })
            ->values();
    }
}