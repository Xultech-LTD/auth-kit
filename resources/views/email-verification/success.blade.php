{{--
/**
 * Page: Email Verified
 *
 * Confirmation page shown after a successful email verification flow.
 *
 * Data:
 * - $status: Success message to display.
 */
--}}

{{-- Configuration & Route Resolution --}}
@php
    $c = (array) config('authkit.components', []);
    $webNames = (array) config('authkit.route_names.web', []);

    $login = (string) ($webNames['login'] ?? 'authkit.web.login');
    $pageComponent = (string) data_get($c, 'page', 'authkit::page');
    $pageKey = (string) data_get(config('authkit.javascript.pages', []), 'email_verification_success.page_key', 'email_verification_success');
@endphp

{{-- Layout Wrapper --}}
<x-dynamic-component :component="$pageComponent" title="Email verified" :page-key="$pageKey">

    {{-- Container --}}
    <x-dynamic-component :component="data_get($c, 'container')">

        {{-- Card --}}
        <x-dynamic-component :component="data_get($c, 'card')">

            {{-- Auth Header --}}
            <x-dynamic-component
                    :component="data_get($c, 'auth_header')"
                    title="Email verified"
                    subtitle="You can now continue."
            />

            {{-- Success Alert --}}
            <x-dynamic-component :component="data_get($c, 'alert')">
                {{ $status }}
            </x-dynamic-component>

            {{-- Primary Action --}}
            <div class="authkit-primary-action">
                <x-dynamic-component :component="data_get($c, 'link')" :href="route($login)">
                    Continue to login
                </x-dynamic-component>
            </div>

        </x-dynamic-component>
    </x-dynamic-component>
</x-dynamic-component>
