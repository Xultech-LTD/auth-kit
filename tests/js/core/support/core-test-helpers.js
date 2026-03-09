/**
 * AuthKit
 * -----------------------------------------------------------------------------
 * File: core-test-helpers.js
 * Author: Michael Erastus
 * Package: AuthKit
 *
 * Description:
 * -----------------------------------------------------------------------------
 * Shared helpers for AuthKit core runtime tests.
 *
 * Responsibilities:
 * - Reset DOM, storage, and browser globals between tests.
 * - Install a predictable AuthKit global configuration payload.
 * - Clear cached runtime configuration between tests.
 * - Reduce repeated setup boilerplate across core test files.
 *
 * Notes:
 * - These helpers are intended only for test environments.
 * - They assume Vitest + jsdom.
 */

import { clearConfigCache } from '../../../../public/authkit/js/core/config.js';


/**
 * Reset browser-like test state used across AuthKit core tests.
 *
 * @returns {void}
 */
export function resetCoreTestEnvironment() {
    document.documentElement.innerHTML = '<head></head><body></body>';
    document.body.innerHTML = '';

    window.localStorage.clear();
    window.sessionStorage.clear();

    delete window.AuthKit;
    delete window.__AUTHKIT__;

    clearConfigCache();
}


/**
 * Install a normalized AuthKit runtime config on the browser global.
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

        pages: {
            login: {
                enabled: true,
                pageKey: 'login',
            },
        },

        ...overrides,
    };

    window.AuthKit = {
        config,
    };

    clearConfigCache();

    return config;
}