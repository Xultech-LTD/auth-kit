/**
 * AuthKit
 * -----------------------------------------------------------------------------
 * File: js/core/config.js
 * Author: Michael Erastus
 * Package: AuthKit
 *
 * Description:
 * -----------------------------------------------------------------------------
 * Runtime configuration helpers for the AuthKit browser client.
 *
 * This file is responsible for reading, normalizing, and exposing the client-side
 * AuthKit configuration object that is made available to the browser runtime.
 *
 * Responsibilities:
 * - Resolve the AuthKit global runtime object key.
 * - Read the AuthKit runtime configuration payload from the browser.
 * - Provide safe access helpers for nested configuration values.
 * - Normalize frequently used configuration structures.
 * - Avoid direct configuration lookups being repeated across modules.
 *
 * Design notes:
 * - The browser runtime should never assume configuration exists.
 * - All configuration access should fail gracefully and return sensible defaults.
 * - This module is intentionally framework-agnostic and browser-native.
 *
 * Expected runtime shape:
 * -----------------------------------------------------------------------------
 * window[window_key] = {
 *   config: {
 *     runtime: { ... },
 *     events: { ... },
 *     modules: { ... },
 *     pages: { ... },
 *   }
 * }
 *
 * Example:
 * -----------------------------------------------------------------------------
 * window.AuthKit = {
 *   config: {
 *     runtime: {
 *       windowKey: 'AuthKit',
 *       dispatchEvents: true,
 *       eventTarget: 'document',
 *     }
 *   }
 * };
 *
 * @package   AuthKit
 * @author    Michael Erastus
 * @license   MIT
 */

import {hasOwn, isObject, isString, isUndefined, normalizeString, toBoolean,} from './helpers.js';


/**
 * Default global object key exposed on window.
 *
 * @type {string}
 */
const DEFAULT_WINDOW_KEY = 'AuthKit';


/**
 * Default runtime configuration.
 *
 * These defaults are used whenever browser configuration is missing or incomplete.
 *
 * @type {Object}
 */
const DEFAULT_CONFIG = {
    runtime: {
        windowKey: DEFAULT_WINDOW_KEY,
        dispatchEvents: true,
        eventTarget: 'document',
    },

    ui: {
        mode: 'system',
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
};


/**
 * Cached merged runtime configuration.
 *
 * This avoids rebuilding the merged config object repeatedly across modules.
 *
 * @type {Object|null}
 */
let cachedConfig = null;


/**
 * Clear the cached merged configuration.
 *
 * @returns {void}
 */
export function clearConfigCache() {
    cachedConfig = null;
}


/**
 * Resolve the AuthKit global window key.
 *
 * This first checks whether a preboot object already exposed a runtime key, then
 * falls back to the package default.
 *
 * @returns {string}
 */
export function getWindowKey() {
    if ( isUndefined(window) ) {
        return DEFAULT_WINDOW_KEY;
    }

    /**
     * Direct check for a known default object.
     */
    if (
        isObject(window[DEFAULT_WINDOW_KEY]) &&
        isObject(window[DEFAULT_WINDOW_KEY].config) &&
        isObject(window[DEFAULT_WINDOW_KEY].config.runtime) &&
        isString(window[DEFAULT_WINDOW_KEY].config.runtime.windowKey)
    ) {
        return normalizeString(
            window[DEFAULT_WINDOW_KEY].config.runtime.windowKey,
            DEFAULT_WINDOW_KEY
        );
    }

    /**
     * Optional generic preboot payload.
     *
     * This allows future layouts to expose the runtime key before the main runtime
     * fully initializes.
     */
    if (
        isObject(window.__AUTHKIT__) &&
        isString(window.__AUTHKIT__.windowKey)
    ) {
        return normalizeString(window.__AUTHKIT__.windowKey, DEFAULT_WINDOW_KEY);
    }

    return DEFAULT_WINDOW_KEY;
}


/**
 * Resolve the AuthKit global object from the browser window.
 *
 * Returns an empty object when no runtime global exists yet.
 *
 * @returns {Object}
 */
export function getGlobalObject() {
    if (isUndefined(window)) {
        return {};
    }

    const windowKey = getWindowKey();
    const candidate = window[windowKey];

    return isObject(candidate) ? candidate : {};
}


/**
 * Resolve the raw AuthKit runtime configuration object.
 *
 * Returns an empty object when configuration is unavailable.
 *
 * @returns {Object}
 */
export function getRawConfig() {
    const globalObject = getGlobalObject();

    if (isObject(globalObject.config)) {
        return globalObject.config;
    }

    return {};
}


/**
 * Get the full merged runtime configuration.
 *
 * This performs a shallow/deep merge for the currently known top-level sections
 * and caches the result for subsequent lookups.
 *
 * @returns {Object}
 */
export function getConfig() {
    if (cachedConfig !== null) {
        return cachedConfig;
    }

    const rawConfig = getRawConfig();

    cachedConfig = {
        ...DEFAULT_CONFIG,
        ...rawConfig,

        runtime: {
            ...DEFAULT_CONFIG.runtime,
            ...(isObject(rawConfig.runtime) ? rawConfig.runtime : {}),
        },

        ui: {
            ...DEFAULT_CONFIG.ui,
            ...(isObject(rawConfig.ui) ? rawConfig.ui : {}),
        },

        events: {
            ...DEFAULT_CONFIG.events,
            ...(isObject(rawConfig.events) ? rawConfig.events : {}),
        },

        modules: {
            ...DEFAULT_CONFIG.modules,
            ...(isObject(rawConfig.modules) ? rawConfig.modules : {}),
            theme: {
                ...DEFAULT_CONFIG.modules.theme,
                ...(isObject(rawConfig.modules?.theme) ? rawConfig.modules.theme : {}),
            },
            forms: {
                ...DEFAULT_CONFIG.modules.forms,
                ...(isObject(rawConfig.modules?.forms) ? rawConfig.modules.forms : {}),
            },
        },

        pages: isObject(rawConfig.pages) ? rawConfig.pages : DEFAULT_CONFIG.pages,
    };

    return cachedConfig;
}


/**
 * Safely resolve a nested configuration value using dot notation.
 *
 * Example:
 * - getConfigValue('runtime.dispatchEvents', true)
 *
 * @param {string} path
 * @param {*} fallback
 * @returns {*}
 */
export function getConfigValue(path, fallback = null) {
    if (!isString(path) || path.trim() === '') {
        return fallback;
    }

    const config = getConfig();
    const segments = path.split('.').filter(Boolean);

    let current = config;

    for (const segment of segments) {
        if (!isObject(current) || !hasOwn(current, segment)) {
            return fallback;
        }

        current = current[segment];
    }

    return current === undefined ? fallback : current;
}


/**
 * Determine whether a runtime configuration path exists.
 *
 * @param {string} path
 * @returns {boolean}
 */
export function hasConfigValue(path) {
    if (!isString(path) || path.trim() === '') {
        return false;
    }

    const config = getConfig();
    const segments = path.split('.').filter(Boolean);

    let current = config;

    for (const segment of segments) {
        if (!isObject(current) || !hasOwn(current, segment)) {
            return false;
        }

        current = current[segment];
    }

    return true;
}


/**
 * Resolve whether a named core module is enabled.
 *
 * Example:
 * - isModuleEnabled('theme')
 * - isModuleEnabled('forms')
 *
 * @param {string} moduleKey
 * @returns {boolean}
 */
export function isModuleEnabled(moduleKey) {
    if (!isString(moduleKey) || moduleKey.trim() === '') {
        return false;
    }

    return toBoolean(
        getConfigValue(`modules.${moduleKey}.enabled`, false)
    );
}


/**
 * Resolve whether a configured page module is enabled.
 *
 * Example:
 * - isPageEnabled('login')
 * - isPageEnabled('password_reset')
 *
 * @param {string} pageKey
 * @returns {boolean}
 */
export function isPageEnabled(pageKey) {
    if (!isString(pageKey) || pageKey.trim() === '') {
        return false;
    }

    return toBoolean(
        getConfigValue(`pages.${pageKey}.enabled`, false)
    );
}


/**
 * Resolve the configured page marker key for a page module.
 *
 * Example:
 * - getPageMarker('login') => "login"
 *
 * @param {string} pageKey
 * @param {string|null} fallback
 * @returns {string|null}
 */
export function getPageMarker(pageKey, fallback = null) {
    if (!isString(pageKey) || pageKey.trim() === '') {
        return fallback;
    }

    return normalizeString(
        getConfigValue(`pages.${pageKey}.page_key`, fallback),
        fallback
    );
}


/**
 * Resolve the configured runtime event target key.
 *
 * @returns {string}
 */
export function getRuntimeEventTarget() {
    return normalizeString(
        getConfigValue('runtime.eventTarget', 'document'),
        'document'
    );
}


/**
 * Resolve whether runtime event dispatching is enabled.
 *
 * @returns {boolean}
 */
export function shouldDispatchEvents() {
    return toBoolean(
        getConfigValue('runtime.dispatchEvents', true)
    );
}


/**
 * Expose default AuthKit client configuration.
 *
 * Useful for diagnostics and testing.
 *
 * @returns {Object}
 */
export function getDefaultConfig() {
    return JSON.parse(JSON.stringify(DEFAULT_CONFIG));
}


/**
 * Set or merge the AuthKit runtime configuration on the global object.
 *
 * This helper is useful during bootstrapping and in test environments.
 *
 * @param {Object} payload
 * @returns {Object}
 */
export function setConfig(payload = {}) {
    if (isUndefined(window)) {
        return {};
    }

    const windowKey = getWindowKey();

    if (!isObject(window[windowKey])) {
        window[windowKey] = {};
    }

    const currentConfig = getConfig();
    const nextConfig = isObject(payload) ? payload : {};

    window[windowKey].config = {
        ...currentConfig,
        ...nextConfig,
        runtime: {
            ...currentConfig.runtime,
            ...(isObject(nextConfig.runtime) ? nextConfig.runtime : {}),
        },
        ui: {
            ...currentConfig.ui,
            ...(isObject(nextConfig.ui) ? nextConfig.ui : {}),
        },
        events: {
            ...currentConfig.events,
            ...(isObject(nextConfig.events) ? nextConfig.events : {}),
        },
        modules: {
            ...currentConfig.modules,
            ...(isObject(nextConfig.modules) ? nextConfig.modules : {}),
            theme: {
                ...currentConfig.modules.theme,
                ...(isObject(nextConfig.modules?.theme) ? nextConfig.modules.theme : {}),
            },
            forms: {
                ...currentConfig.modules.forms,
                ...(isObject(nextConfig.modules?.forms) ? nextConfig.modules.forms : {}),
            },
        },
        pages: {
            ...(isObject(currentConfig.pages) ? currentConfig.pages : {}),
            ...(isObject(nextConfig.pages) ? nextConfig.pages : {}),
        },
    };

    clearConfigCache();

    return getConfig();
}