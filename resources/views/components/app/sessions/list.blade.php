{{--
/**
 * Component: Sessions List
 *
 * Reusable authenticated sessions list for AuthKit.
 *
 * Purpose:
 * - Render normalized authenticated session data in a clean card list.
 * - Display an empty state when database-backed session tracking is unavailable
 *   or when no sessions are present.
 *
 * Expected session item shape:
 * [
 *   'id' => '...',
 *   'ip_address' => '127.0.0.1',
 *   'user_agent' => 'Mozilla/5.0 ...',
 *   'device' => 'Desktop device',
 *   'browser' => 'Chrome',
 *   'platform' => 'Windows',
 *   'is_current' => true,
 *   'last_activity_at' => Carbon|null,
 *   'last_activity_human' => '2 minutes ago',
 * ]
 *
 * Props:
 * - sessions: Iterable normalized sessions list.
 * - supportsSessionTracking: Whether the application supports database session tracking.
 */
--}}

@props([
    'sessions' => collect(),
    'supportsSessionTracking' => true,
])

@php
    $resolvedSessions = collect($sessions ?? [])->values();
    $trackingEnabled = (bool) $supportsSessionTracking;
@endphp

<div {{ $attributes->merge(['class' => 'authkit-app-sessions']) }}>
    @if (! $trackingEnabled)
        <div class="authkit-app-sessions__empty">
            <h3 class="authkit-app-sessions__empty-title">
                Session tracking is unavailable
            </h3>

            <p class="authkit-app-sessions__empty-text">
                AuthKit can only display active sessions when your application is using
                the database session driver and the sessions table is available.
            </p>
        </div>
    @elseif ($resolvedSessions->isEmpty())
        <div class="authkit-app-sessions__empty">
            <h3 class="authkit-app-sessions__empty-title">
                No active sessions found
            </h3>

            <p class="authkit-app-sessions__empty-text">
                There are no additional tracked authenticated sessions available for
                this account right now.
            </p>
        </div>
    @else
        <div class="authkit-app-sessions__list">
            @foreach ($resolvedSessions as $session)
                @php
                    $device = (string) ($session['device'] ?? 'Unknown device');
                    $browser = (string) ($session['browser'] ?? 'Unknown browser');
                    $platform = (string) ($session['platform'] ?? 'Unknown platform');
                    $ipAddress = (string) ($session['ip_address'] ?? 'Unknown');
                    $userAgent = (string) ($session['user_agent'] ?? 'Unknown user agent');
                    $isCurrent = (bool) ($session['is_current'] ?? false);
                    $lastActivityHuman = (string) ($session['last_activity_human'] ?? 'Unknown');
                @endphp

                <article class="authkit-app-session-card{{ $isCurrent ? ' authkit-app-session-card--current' : '' }}">
                    <div class="authkit-app-session-card__header">
                        <div class="authkit-app-session-card__identity">
                            <h3 class="authkit-app-session-card__device">
                                {{ $device }}
                            </h3>

                            <p class="authkit-app-session-card__meta">
                                {{ $browser }} · {{ $platform }}
                            </p>
                        </div>

                        @if ($isCurrent)
                            <div class="authkit-app-session-card__badge">
                                Current session
                            </div>
                        @endif
                    </div>

                    <div class="authkit-app-session-card__details">
                        <div class="authkit-app-session-card__detail">
                            <div class="authkit-app-session-card__detail-label">
                                IP address
                            </div>

                            <div class="authkit-app-session-card__detail-value">
                                {{ $ipAddress }}
                            </div>
                        </div>

                        <div class="authkit-app-session-card__detail">
                            <div class="authkit-app-session-card__detail-label">
                                Last activity
                            </div>

                            <div class="authkit-app-session-card__detail-value">
                                {{ $lastActivityHuman }}
                            </div>
                        </div>
                    </div>

                    <div class="authkit-app-session-card__agent">
                        {{ $userAgent }}
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</div>