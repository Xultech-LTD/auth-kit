{{--
/**
 * Layout: Auth Application Page
 *
 * High-level wrapper view for AuthKit pages.
 *
 * Responsibilities:
 * - Renders the root AuthKit document layout component.
 * - Provides a page-level shell and stable page hooks.
 * - Exposes a centered page container for AuthKit page content.
 *
 * Notes:
 * - This is a Blade view wrapper, not the root document shell itself.
 * - The root <html>, <head>, asset loading, and UI mode handling are managed by
 *   the `authkit::layout` component.
 * - Consumers may publish and customize this file to integrate AuthKit pages
 *   into their own application shells if desired.
 */
--}}

<x-authkit::layout
        :title="$title ?? 'AuthKit'"
        :theme="$theme ?? null"
        :engine="$engine ?? null"
        :mode="$mode ?? null"
>
    <main class="authkit-page authkit-auth-page">
        <div class="authkit-page-container">
            @yield('content')
        </div>
    </main>
</x-authkit::layout>