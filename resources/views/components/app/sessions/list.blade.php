{{--
/**
 * Component: App Sessions List
 *
 * Authenticated session list for AuthKit.
 *
 * Purpose:
 * - Render normalized authenticated session entries.
 * - Highlight the current session.
 * - Present device, browser, platform, IP, and activity metadata.
 *
 * Props:
 * - sessions: Collection|array of normalized session items.
 * - supportsSessionTracking: Whether database-backed session tracking is available.
 */
--}}

@props([
    'sessions' => [],
    'supportsSessionTracking' => true,
])

@php
    $resolvedSessions = collect($sessions ?? [])->values();
@endphp

<div class="authkit-app-sessions">
    @if (! $supportsSessionTracking)
        <div class="authkit-app-sessions__empty">
            <div class="authkit-app-sessions__empty-title">
                Session tracking is unavailable
            </div>

            <p class="authkit-app-sessions__empty-text">
                This application is not currently using the database session driver,
                so active authenticated sessions cannot be listed here yet.
            </p>
        </div>
    @elseif ($resolvedSessions->isEmpty())
        <div class="authkit-app-sessions__empty">
            <div class="authkit-app-sessions__empty-title">
                No active sessions found
            </div>

            <p class="authkit-app-sessions__empty-text">
                We could not find any database-backed authenticated sessions for this account.
            </p>
        </div>
    @else
        <div class="authkit-app-sessions__list">
            @foreach ($resolvedSessions as $session)
                @php
                    $isCurrent = (bool) ($session['is_current'] ?? false);
                @endphp

                <article class="authkit-app-session-card{{ $isCurrent ? ' authkit-app-session-card--current' : '' }}">
                    <div class="authkit-app-session-card__header">
                        <div class="authkit-app-session-card__identity">
                            <div class="authkit-app-session-card__device">
                                {{ $session['device'] ?? 'Device' }}
                            </div>

                            <div class="authkit-app-session-card__meta">
                                {{ $session['browser'] ?? 'Unknown browser' }}
                                ·
                                {{ $session['platform'] ?? 'Unknown platform' }}
                            </div>
                        </div>

                        @if ($isCurrent)
                            <span class="authkit-app-session-card__badge">
                                Current session
                            </span>
                        @endif
                    </div>

                    <div class="authkit-app-session-card__details">
                        <div class="authkit-app-session-card__detail">
                            <span class="authkit-app-session-card__detail-label">IP address</span>
                            <span class="authkit-app-session-card__detail-value">
                                {{ $session['ip_address'] ?? 'Unknown' }}
                            </span>
                        </div>

                        <div class="authkit-app-session-card__detail">
                            <span class="authkit-app-session-card__detail-label">Last activity</span>
                            <span class="authkit-app-session-card__detail-value">
                                {{ $session['last_activity_human'] ?? 'Unknown' }}
                            </span>
                        </div>
                    </div>

                    @if (!empty($session['user_agent']))
                        <div class="authkit-app-session-card__agent">
                            {{ $session['user_agent'] }}
                        </div>
                    @endif
                </article>
            @endforeach
        </div>
    @endif
</div>