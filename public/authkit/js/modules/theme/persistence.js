/**
 * AuthKit
 * -----------------------------------------------------------------------------
 * File: persistence.js
 * Author: Michael Erastus
 * Package: AuthKit
 *
 * Description:
 * -----------------------------------------------------------------------------
 * Theme mode persistence utilities for the AuthKit browser runtime.
 *
 * This file is responsible for reading and writing the user's preferred
 * appearance mode to browser storage when theme persistence is enabled.
 *
 * Responsibilities:
 * - Resolve whether theme mode persistence is enabled.
 * - Resolve the configured browser storage key for theme mode persistence.
 * - Read a persisted appearance mode from browser storage.
 * - Persist a valid appearance mode to browser storage.
 * - Clear a persisted appearance mode from browser storage.
 *
 * Design notes:
 * - This file does not write theme state to the DOM.
 * - This file does not resolve system/browser preference logic directly.
 * - This file delegates low-level browser storage access to core/storage.js.
 * - Invalid or unsupported persisted values are ignored safely.
 *
 * Configuration paths:
 * - ui.persistence.enabled
 * - ui.persistence.storageKey
 *
 * Persisted values:
 * - light
 * - dark
 * - system
 *
 * @package   AuthKit
 * @author    Michael Erastus
 * @license   MIT
 */

import { getConfigValue } from '../../core/config.js';
import { getItem, removeItem, setItem } from '../../core/storage.js';
import { normalizeString, toBoolean } from '../../core/helpers.js';
import { isSupportedMode, normalizeMode } from './resolve-mode.js';


/**
 * Default browser storage key used for persisted theme mode.
 *
 * @type {string}
 */
export const DEFAULT_THEME_STORAGE_KEY = 'authkit.ui.mode';


/**
 * Determine whether theme mode persistence is enabled.
 *
 * Configuration path:
 * - ui.persistence.enabled
 *
 * @returns {boolean}
 */
export function isPersistenceEnabled() {
    return toBoolean(
        getConfigValue('ui.persistence.enabled', true)
    );
}


/**
 * Resolve the configured browser storage key used for theme mode persistence.
 *
 * Configuration path:
 * - ui.persistence.storageKey
 *
 * @returns {string}
 */
export function getStorageKey() {
    return normalizeString(
        getConfigValue('ui.persistence.storageKey', DEFAULT_THEME_STORAGE_KEY),
        DEFAULT_THEME_STORAGE_KEY
    );
}


/**
 * Read the raw persisted appearance mode value from browser storage.
 *
 * This function does not validate the returned value beyond basic string
 * normalization.
 *
 * @returns {string|null}
 */
export function readRawPersistedMode() {
    if (!isPersistenceEnabled()) {
        return null;
    }

    const storageKey = getStorageKey();

    return normalizeString(
        getItem(storageKey, null, 'local'),
        null
    );
}


/**
 * Read the persisted appearance mode from browser storage.
 *
 * Only supported mode values are returned:
 * - light
 * - dark
 * - system
 *
 * Invalid persisted values are treated as absent and return null.
 *
 * @returns {string|null}
 */
export function readPersistedMode() {
    const persistedMode = readRawPersistedMode();

    if (!isSupportedMode(persistedMode)) {
        return null;
    }

    return normalizeMode(persistedMode, 'system');
}


/**
 * Persist a preferred appearance mode to browser storage.
 *
 * Persistence is skipped when:
 * - theme persistence is disabled
 * - the supplied mode is invalid
 *
 * Supported values:
 * - light
 * - dark
 * - system
 *
 * @param {*} mode
 * @returns {boolean}
 */
export function persistMode(mode) {
    if (!isPersistenceEnabled()) {
        return false;
    }

    if (!isSupportedMode(mode)) {
        return false;
    }

    const storageKey = getStorageKey();
    const normalizedMode = normalizeMode(mode, 'system');

    return setItem(storageKey, normalizedMode, 'local');
}


/**
 * Clear the persisted appearance mode from browser storage.
 *
 * @returns {boolean}
 */
export function clearPersistedMode() {
    if (!isPersistenceEnabled()) {
        return false;
    }

    return removeItem(getStorageKey(), 'local');
}


/**
 * Resolve the effective persisted preference state.
 *
 * Return shape:
 * - enabled: whether persistence is enabled
 * - storageKey: resolved browser storage key
 * - persistedMode: validated persisted mode or null
 *
 * @returns {{
 *   enabled: boolean,
 *   storageKey: string,
 *   persistedMode: string|null
 * }}
 */
export function getPersistenceState() {
    return {
        enabled: isPersistenceEnabled(),
        storageKey: getStorageKey(),
        persistedMode: readPersistedMode(),
    };
}