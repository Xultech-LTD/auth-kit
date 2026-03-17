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

        <section class="authkit-dashboard__grid">
            <article class="authkit-dashboard-card authkit-dashboard-card--wide">
                <div class="authkit-dashboard-card__header">
                    <div>
                        <h3 class="authkit-dashboard-card__title">Session activity</h3>
                        <p class="authkit-dashboard-card__text">
                            These are the currently stored authenticated sessions associated
                            with your account.
                        </p>
                    </div>
                </div>

                <x-authkit::app.sessions.list
                        :sessions="$sessions ?? collect()"
                        :supports-session-tracking="$supportsSessionTracking ?? true"
                />
            </article>
        </section>
    </div>
</x-authkit::app.layout>