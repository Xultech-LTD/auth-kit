/**
 * AuthKit
 * -----------------------------------------------------------------------------
 * File: js/core/storage.js
 * Author: Michael Erastus
 * Package: AuthKit
 *
 * Description:
 * -----------------------------------------------------------------------------
 * Safe browser storage helpers for the AuthKit client runtime.
 *
 * This file centralizes all browser storage access so runtime modules do not
 * interact with localStorage or sessionStorage directly.
 *
 * Responsibilities:
 * - Provide safe read/write/remove helpers for browser storage.
 * - Fail gracefully when storage is unavailable or blocked.
 * - Offer a small, consistent API for runtime modules.
 * - Support future runtime persistence needs without duplicating logic.
 *
 * Design notes:
 * - Storage access may fail in some environments, including privacy-restricted
 *   browsers, sandboxed contexts, or server-side rendering scenarios.
 * - All helpers in this file must fail safely and never throw intentionally.
 * - This file should remain generic and must not contain theme- or form-
 *   specific business logic.
 *
 * @package   AuthKit
 * @author    Michael Erastus
 * @license   MIT
 */

import {isObject, isString, isUndefined, normalizeString} from './helpers.js';


/**
 * Resolve a browser storage implementation safely.
 *
 * Supported storage types:
 * - local
 * - session
 *
 * @param {string} type
 * @returns {Storage|null}
 */
export function resolveStorage(type = 'local') {
    try {
        if (isUndefined(window)) {
            return null;
        }

        if (type === 'session') {
            return window.sessionStorage ?? null;
        }

        return window.localStorage ?? null;
    } catch (error) {
        return null;
    }
}


/**
 * Determine whether a storage implementation is usable.
 *
 * This performs a lightweight write/remove probe because some browsers expose
 * the storage object but still block usage.
 *
 * @param {string} type
 * @returns {boolean}
 */
export function isStorageAvailable(type = 'local') {
    const storage = resolveStorage(type);

    if (!storage) {
        return false;
    }

    try {
        const probeKey = '__authkit_storage_probe__';

        storage.setItem(probeKey, '1');
        storage.removeItem(probeKey);

        return true;
    } catch (error) {
        return false;
    }
}


/**
 * Read a raw string value from browser storage.
 *
 * Returns the provided fallback when:
 * - storage is unavailable
 * - the key is invalid
 * - the key does not exist
 *
 * @param {string} key
 * @param {string|null} fallback
 * @param {string} type
 * @returns {string|null}
 */
export function getItem(key, fallback = null, type = 'local') {
    const normalizedKey = normalizeString(key, '');

    if (normalizedKey === '') {
        return fallback;
    }

    const storage = resolveStorage(type);

    if (!storage) {
        return fallback;
    }

    try {
        const value = storage.getItem(normalizedKey);

        return value !== null ? value : fallback;
    } catch (error) {
        return fallback;
    }
}


/**
 * Write a raw string value to browser storage.
 *
 * @param {string} key
 * @param {*} value
 * @param {string} type
 * @returns {boolean}
 */
export function setItem(key, value, type = 'local') {
    const normalizedKey = normalizeString(key, '');

    if (normalizedKey === '') {
        return false;
    }

    if (value !== null && isObject(value)) {
        return false;
    }

    const storage = resolveStorage(type);

    if (!storage) {
        return false;
    }

    try {
        storage.setItem(normalizedKey, String(value));
        return true;
    } catch (error) {
        return false;
    }
}


/**
 * Remove a value from browser storage.
 *
 * @param {string} key
 * @param {string} type
 * @returns {boolean}
 */
export function removeItem(key, type = 'local') {
    const normalizedKey = normalizeString(key, '');

    if (normalizedKey === '') {
        return false;
    }

    const storage = resolveStorage(type);

    if (!storage) {
        return false;
    }

    try {
        storage.removeItem(normalizedKey);
        return true;
    } catch (error) {
        return false;
    }
}


/**
 * Read a JSON value from browser storage.
 *
 * Returns the fallback when:
 * - storage is unavailable
 * - the key is invalid
 * - the key does not exist
 * - the stored value is invalid JSON
 *
 * @param {string} key
 * @param {*} fallback
 * @param {string} type
 * @returns {*}
 */
export function getJson(key, fallback = null, type = 'local') {
    const rawValue = getItem(key, null, type);

    if (!isString(rawValue) || rawValue.trim() === '') {
        return fallback;
    }

    try {
        return JSON.parse(rawValue);
    } catch (error) {
        return fallback;
    }
}


/**
 * Write a JSON-serializable value to browser storage.
 *
 * @param {string} key
 * @param {*} value
 * @param {string} type
 * @returns {boolean}
 */
export function setJson(key, value, type = 'local') {
    const normalizedKey = normalizeString(key, '');

    if (normalizedKey === '') {
        return false;
    }

    const storage = resolveStorage(type);

    if (!storage) {
        return false;
    }

    try {
        storage.setItem(normalizedKey, JSON.stringify(value));
        return true;
    } catch (error) {
        return false;
    }
}


/**
 * Create a storage adapter object for a specific storage type.
 *
 * This is useful when a module wants to work with a fixed storage backend
 * through a compact API.
 *
 * @param {string} type
 * @returns {{
 *   type: string,
 *   available: () => boolean,
 *   get: (key: string, fallback?: string|null) => string|null,
 *   set: (key: string, value: *) => boolean,
 *   remove: (key: string) => boolean,
 *   getJson: (key: string, fallback?: *) => *,
 *   setJson: (key: string, value: *) => boolean
 * }}
 */
export function createStorage(type = 'local') {
    return {
        type,

        available() {
            return isStorageAvailable(type);
        },

        get(key, fallback = null) {
            return getItem(key, fallback, type);
        },

        set(key, value) {
            return setItem(key, value, type);
        },

        remove(key) {
            return removeItem(key, type);
        },

        getJson(key, fallback = null) {
            return getJson(key, fallback, type);
        },

        setJson(key, value) {
            return setJson(key, value, type);
        },
    };
}