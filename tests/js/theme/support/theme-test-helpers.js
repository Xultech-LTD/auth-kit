/**
 * AuthKit
 * -----------------------------------------------------------------------------
 * File: theme-test-helpers.js
 * Author: Michael Erastus
 * Package: AuthKit
 *
 * Description:
 * -----------------------------------------------------------------------------
 * Shared test helpers for AuthKit theme module tests.
 *
 * Responsibilities:
 * - Reset browser-like test state between runs.
 * - Install a predictable AuthKit runtime config on window.
 * - Provide a controllable matchMedia mock for theme tests.
 * - Reduce repeated setup logic across theme test files.
 *
 * Notes:
 * - These helpers are intended only for test environments.
 * - They assume Vitest + jsdom.
 */

import { clearConfigCache } from '../../../../public/authkit/js/core/config.js';


/**
 * Reset document and storage state used by theme tests.
 *
 * @returns {void}
 */
export function resetThemeTestEnvironment() {
    document.documentElement.removeAttribute('data-authkit-mode');
    document.documentElement.removeAttribute('data-authkit-mode-preference');
    document.documentElement.removeAttribute('data-authkit-mode-resolved');

    document.body.innerHTML = '';
    window.localStorage.clear();

    delete window.AuthKit;

    clearConfigCache();
}


/**
 * Install a normalized AuthKit runtime config on the browser global.
 *
 * This helper uses the camelCase JS runtime config shape expected by the
 * AuthKit browser code.
 *
 * @param {Object} [overrides={}]
 * @returns {Object}
 */
export function installAuthKitConfig(overrides = {}) {
    const config = {
        runtime: {
            windowKey: 'AuthKit',
            dispatchEvents: true,
            eventTarget: 'document',
        },

        ui: {
            mode: 'system',
            persistence: {
                enabled: true,
                storageKey: 'authkit.ui.mode',
            },
            toggle: {
                attribute: 'data-authkit-theme-toggle',
                allowSystem: true,
            },
        },

        events: {
            ready: 'authkit:ready',
            theme_ready: 'authkit:theme:ready',
            theme_changed: 'authkit:theme:changed',
            form_before_submit: 'authkit:form:before-submit',
            form_success: 'authkit:form:success',
            form_error: 'authkit:form:error',
            page_ready: 'authkit:page:ready',
        },

        modules: {
            theme: {
                enabled: true,
            },
            forms: {
                enabled: true,
            },
        },

        pages: {},
        ...overrides,
    };

    window.AuthKit = {
        config,
    };

    clearConfigCache();

    return config;
}


/**
 * Create and install a controllable matchMedia mock.
 *
 * The returned object allows tests to:
 * - inspect the current dark-mode state
 * - toggle the media query match state
 * - manually notify registered listeners
 *
 * Supported APIs:
 * - addEventListener/removeEventListener
 * - addListener/removeListener
 *
 * @param {boolean} [initialDark=false]
 * @returns {{
 *   mediaQueryList: MediaQueryList,
 *   setDark: (value: boolean) => void,
 *   emitChange: () => void
 * }}
 */
export function installMatchMediaMock(initialDark = false) {
    let isDark = Boolean(initialDark);

    const changeListeners = new Set();
    const legacyListeners = new Set();

    const mediaQueryList = {
        matches: isDark,
        media: '(prefers-color-scheme: dark)',

        addEventListener(eventName, listener) {
            if (eventName === 'change' && typeof listener === 'function') {
                changeListeners.add(listener);
            }
        },

        removeEventListener(eventName, listener) {
            if (eventName === 'change' && typeof listener === 'function') {
                changeListeners.delete(listener);
            }
        },

        addListener(listener) {
            if (typeof listener === 'function') {
                legacyListeners.add(listener);
            }
        },

        removeListener(listener) {
            if (typeof listener === 'function') {
                legacyListeners.delete(listener);
            }
        },
    };

    window.matchMedia = (query) => {
        if (query !== '(prefers-color-scheme: dark)') {
            return {
                ...mediaQueryList,
                media: query,
            };
        }

        return mediaQueryList;
    };

    return {
        mediaQueryList,

        setDark(value) {
            isDark = Boolean(value);
            mediaQueryList.matches = isDark;
        },

        emitChange() {
            const event = {
                matches: mediaQueryList.matches,
                media: mediaQueryList.media,
            };

            changeListeners.forEach((listener) => listener(event));
            legacyListeners.forEach((listener) => listener(event));
        },
    };
}