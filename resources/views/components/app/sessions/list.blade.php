{{--
/**
 * Component: Sessions List
 *
 * Session list renderer for authenticated AuthKit session/account pages.
 *
 * Responsibilities:
 * - Renders a normalized collection of active/recent sessions.
 * - Provides stable hooks for current-session highlighting and metadata display.
 * - Supports optional per-session actions via a named slot.
 *
 * Props:
 * - sessions: Iterable session records to render.
 * - emptyTitle: Empty-state title text.
 * - emptyDescription: Empty-state supporting text.
 *
 * Expected session item shape (recommended):
 * - id: unique session identifier
 * - is_current: whether the session is the current session
 * - device: device/client label
 * - browser: browser label
 * - platform: platform / OS label
 * - ip_address: session IP address
 * - location: optional approximate location text
 * - last_active_at: human-readable last active text
 * - last_active: alternate last active label
 *
 * Slots:
 * - actions: Optional slot rendered for each session item.
 *   Available variables:
 *   - $session
 *   - $index
 *
 * Notes:
 * - This component is intentionally display-oriented.
 * - Session normalization should happen in the controller/view-model layer.
 * - Consumers may override this component to add richer device icons,
 *   geo metadata, revoke controls, or compact/mobile-specific layouts.
 */
--}}

@props([
    'sessions' => [],
    'emptyTitle' => 'No active sessions',
    'emptyDescription' => 'There are no sessions to display right now.',
])

@php
    $items = collect($sessions)->values();

    $actions = $actions ?? null;
@endphp

<div
        {{ $attributes->merge([
            'class' => 'authkit-sessions-list',
            'data-authkit-sessions-list' => '1',
        ]) }}
>
    @if ($items->isEmpty())
        <div class="authkit-sessions-list__empty">
            <p class="authkit-sessions-list__empty-title">
                {{ $emptyTitle }}
            </p>

            <p class="authkit-sessions-list__empty-description">
                {{ $emptyDescription }}
            </p>
        </div>
    @else
        <div class="authkit-sessions-list__items">
            @foreach ($items as $index => $session)
                @php
                    $device = (string) (
                        data_get($session, 'device')
                        ?: data_get($session, 'browser')
                        ?: 'Unknown device'
                    );

                    $browser = (string) data_get($session, 'browser', '');
                    $platform = (string) data_get($session, 'platform', '');
                    $ipAddress = (string) data_get($session, 'ip_address', '');
                    $location = (string) data_get($session, 'location', '');
                    $lastActive = (string) (
                        data_get($session, 'last_active_at')
                        ?: data_get($session, 'last_active')
                        ?: ''
                    );

                    $isCurrent = (bool) data_get($session, 'is_current', false);
                    $sessionKey = (string) (data_get($session, 'id') ?: $index);
                @endphp

                <article
                        class="authkit-sessions-list__item{{ $isCurrent ? ' authkit-sessions-list__item--current' : '' }}"
                        data-authkit-session-item="{{ $sessionKey }}"
                >
                    <div class="authkit-sessions-list__item-main">
                        <div class="authkit-sessions-list__item-header">
                            <h3 class="authkit-sessions-list__item-title">
                                {{ $device }}
                            </h3>

                            @if ($isCurrent)
                                <span class="authkit-sessions-list__item-badge">
                                    Current session
                                </span>
                            @endif
                        </div>

                        <div class="authkit-sessions-list__item-meta">
                            @if ($browser !== '')
                                <span class="authkit-sessions-list__item-meta-part">
                                    {{ $browser }}
                                </span>
                            @endif

                            @if ($platform !== '')
                                <span class="authkit-sessions-list__item-meta-part">
                                    {{ $platform }}
                                </span>
                            @endif

                            @if ($ipAddress !== '')
                                <span class="authkit-sessions-list__item-meta-part">
                                    {{ $ipAddress }}
                                </span>
                            @endif

                            @if ($location !== '')
                                <span class="authkit-sessions-list__item-meta-part">
                                    {{ $location }}
                                </span>
                            @endif

                            @if ($lastActive !== '')
                                <span class="authkit-sessions-list__item-meta-part">
                                    Last active {{ $lastActive }}
                                </span>
                            @endif
                        </div>
                    </div>

                    @if ($actions !== null)
                        <div class="authkit-sessions-list__item-actions">
                            {{ $actions(['session' => $session, 'index' => $index]) }}
                        </div>
                    @endif
                </article>
            @endforeach
        </div>
    @endif
</div>