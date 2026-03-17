{{--
/**
 * Page: App Sessions
 *
 * Authenticated AuthKit sessions page.
 *
 * Responsibilities:
 * - Render the authenticated app layout.
 * - Display active session overview content.
 * - Show authenticated session entries through the shared sessions-list component.
 *
 * Expected data:
 * - $title
 * - $heading
 * - $pageKey
 * - $currentPage
 * - $sessions
 * - $supportsSessionTracking
 */
--}}

@php
    $components = (array) config('authkit.components', []);
    $settingsSectionComponent = (string) ($components['settings_section'] ?? 'authkit::app.settings.section');
    $sessionListComponent = (string) ($components['session_list'] ?? 'authkit::app.sessions.list');
@endphp

<x-authkit::app.layout
        :title="$title ?? 'Sessions'"
        :page-key="$pageKey ?? 'sessions'"
        :current-page="$currentPage ?? 'sessions'"
        :page-title="$title ?? 'Sessions'"
        :page-heading="$heading ?? 'Manage active sessions'"
>
    <div class="authkit-dashboard">
        <section class="authkit-dashboard__hero">
            <div class="authkit-dashboard__hero-copy">
                <div class="authkit-dashboard__eyebrow">
                    Account security
                </div>

                <h2 class="authkit-dashboard__hero-title">
                    Active sessions
                </h2>

                <p class="authkit-dashboard__hero-text">
                    Review where your account is currently signed in. This helps you
                    identify recent activity across browsers and devices.
                </p>
            </div>

            <div class="authkit-dashboard__hero-meta">
                <div class="authkit-dashboard__hero-chip">
                    <span class="authkit-dashboard__hero-chip-label">Tracked sessions</span>
                    <span class="authkit-dashboard__hero-chip-value">
                        {{ collect($sessions ?? [])->count() }}
                    </span>
                </div>
            </div>
        </section>

        <div class="authkit-dashboard__stack">
            <x-dynamic-component
                    :component="$settingsSectionComponent"
                    title="Session activity"
                    description="These are the currently stored authenticated sessions associated with your account."
            >
                <x-dynamic-component
                        :component="$sessionListComponent"
                        :sessions="$sessions ?? collect()"
                        :supports-session-tracking="$supportsSessionTracking ?? true"
                />
            </x-dynamic-component>
        </div>
    </div>
</x-authkit::app.layout>