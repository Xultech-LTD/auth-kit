/**
 * AuthKit
 * -----------------------------------------------------------------------------
 * File: js/core/page.js
 * Author: Michael Erastus
 * Package: AuthKit
 *
 * Description:
 * -----------------------------------------------------------------------------
 * Page-resolution helpers for the AuthKit client runtime.
 *
 * This file centralizes how the runtime identifies the active AuthKit page,
 * resolves page module configuration, and decides whether a page-specific
 * browser module should boot.
 *
 * Responsibilities:
 * - Resolve the current AuthKit page key from the DOM.
 * - Resolve configured page module definitions from runtime configuration.
 * - Determine whether a configured page module is enabled.
 * - Provide a stable API for page-module bootstrapping.
 *
 * Design notes:
 * - Page detection is DOM-driven and relies on the page shell marker:
 *   data-authkit-page="..."
 * - Page configuration is runtime-driven and should not be hard-coded in
 *   page modules.
 * - This file must remain small and framework-agnostic.
 *
 * @package   AuthKit
 * @author    Michael Erastus
 * @license   MIT
 */

import { getConfigValue } from './config.js';
import { getPageElement, getPageKey as getDomPageKey } from './dom.js';
import { dataGet, isObject, isString, normalizeString } from './helpers.js';


/**
 * Resolve the configured page runtime map.
 *
 * Expected shape:
 * {
 *   login: { enabled: true, pageKey: 'login' },
 *   register: { enabled: true, pageKey: 'register' }
 * }
 *
 * @returns {Record<string, Object>}
 */
export function getPageConfigs() {
    const pages = getConfigValue('pages', {});

    return isObject(pages) ? pages : {};
}


/**
 * Resolve a configured page definition by its runtime page-module key.
 *
 * Example keys:
 * - login
 * - register
 * - password_reset
 *
 * @param {string} key
 * @returns {Object|null}
 */
export function getPageConfig(key) {
    const normalizedKey = normalizeString(key, '');

    if (normalizedKey === '') {
        return null;
    }

    const pageConfig = dataGet(getPageConfigs(), normalizedKey, null);

    return isObject(pageConfig) ? pageConfig : null;
}


/**
 * Resolve the current AuthKit page key from the DOM marker.
 *
 * Expected DOM marker:
 * - data-authkit-page="login"
 *
 * @returns {string|null}
 */
export function getCurrentPageKey() {
    return getDomPageKey();
}


/**
 * Resolve the current AuthKit page element.
 *
 * @returns {HTMLElement|null}
 */
export function getCurrentPageElement() {
    return getPageElement();
}


/**
 * Determine whether the current page marker matches the given page key.
 *
 * @param {string} key
 * @returns {boolean}
 */
export function isCurrentPage(key) {
    const expectedKey = normalizeString(key, '');
    const currentKey = getCurrentPageKey();

    if (expectedKey === '' || currentKey === null) {
        return false;
    }

    return currentKey === expectedKey;
}


/**
 * Resolve the configured DOM page key for a page runtime entry.
 *
 * Example:
 * - runtime page config key: "password_reset"
 * - configured pageKey: "password_reset"
 *
 * Falls back to the runtime page config key itself when a specific pageKey
 * override is not configured.
 *
 * @param {string} key
 * @returns {string|null}
 */
export function getConfiguredPageKey(key) {
    const normalizedKey = normalizeString(key, '');

    if (normalizedKey === '') {
        return null;
    }

    const pageConfig = getPageConfig(normalizedKey);

    if (!pageConfig) {
        return normalizedKey;
    }

    return normalizeString(pageConfig.pageKey, normalizedKey);
}


/**
 * Determine whether a page runtime entry is enabled.
 *
 * Missing configuration defaults to false so that the runtime only boots
 * page modules that are explicitly registered.
 *
 * @param {string} key
 * @returns {boolean}
 */
export function isPageEnabled(key) {
    const pageConfig = getPageConfig(key);

    if (!pageConfig) {
        return false;
    }

    return Boolean(pageConfig.enabled);
}


/**
 * Determine whether a given page runtime entry matches the current page.
 *
 * This compares:
 * - the configured pageKey for the page runtime entry
 * - the page key detected from the DOM marker
 *
 * @param {string} key
 * @returns {boolean}
 */
export function matchesCurrentPage(key) {
    const configuredPageKey = getConfiguredPageKey(key);
    const currentPageKey = getCurrentPageKey();

    if (!isString(configuredPageKey) || configuredPageKey.trim() === '') {
        return false;
    }

    if (!isString(currentPageKey) || currentPageKey.trim() === '') {
        return false;
    }

    return configuredPageKey === currentPageKey;
}


/**
 * Resolve the active page runtime entry from configured pages.
 *
 * This scans configured page entries and returns the first enabled page
 * definition whose configured page key matches the current DOM page marker.
 *
 * Return shape:
 * {
 *   key: 'login',
 *   pageKey: 'login',
 *   config: { enabled: true, pageKey: 'login' }
 * }
 *
 * @returns {{key: string, pageKey: string, config: Object}|null}
 */
export function resolveActivePage() {
    const currentPageKey = getCurrentPageKey();

    if (!isString(currentPageKey) || currentPageKey.trim() === '') {
        return null;
    }

    const pageConfigs = getPageConfigs();
    const entries = Object.entries(pageConfigs);

    for (const [key, config] of entries) {
        if (!isObject(config) || !isPageEnabled(key)) {
            continue;
        }

        const pageKey = getConfiguredPageKey(key);

        if (!isString(pageKey) || pageKey.trim() === '') {
            continue;
        }

        if (pageKey === currentPageKey) {
            return {
                key,
                pageKey,
                config,
            };
        }
    }

    return null;
}


/**
 * Determine whether the current page has a bootable configured page module.
 *
 * @returns {boolean}
 */
export function hasActivePage() {
    return resolveActivePage() !== null;
}


/**
 * Build a normalized page context payload.
 *
 * This payload is useful when dispatching page lifecycle events or when
 * passing page metadata into runtime modules.
 *
 * @returns {{
 *   key: string|null,
 *   pageKey: string|null,
 *   element: HTMLElement|null,
 *   config: Object|null
 * }}
 */
export function getPageContext() {
    const activePage = resolveActivePage();

    if (!activePage) {
        return {
            key: null,
            pageKey: getCurrentPageKey(),
            element: getCurrentPageElement(),
            config: null,
        };
    }

    return {
        key: activePage.key,
        pageKey: activePage.pageKey,
        element: getCurrentPageElement(),
        config: activePage.config,
    };
}