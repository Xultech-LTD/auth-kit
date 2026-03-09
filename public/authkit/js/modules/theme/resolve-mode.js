/**
 * AuthKit
 * -----------------------------------------------------------------------------
 * File: resolve-mode.js
 * Author: Michael Erastus
 * Package: AuthKit
 *
 * Description:
 * -----------------------------------------------------------------------------
 * Theme mode resolution utilities for the AuthKit browser runtime.
 *
 * This file is responsible for determining:
 * - the configured appearance mode
 * - the persisted user preference
 * - the system/browser preferred appearance
 * - the final resolved mode that should be applied
 *
 * Responsibilities:
 * - Normalize appearance mode values.
 * - Read the configured default mode from runtime configuration.
 * - Resolve the browser/system preferred color scheme.
 * - Determine the preferred mode and the final resolved mode.
 * - Provide a single, stable source of truth for theme mode resolution.
 *
 * Design notes:
 * - This file does not write to the DOM.
 * - This file does not persist values directly.
 * - This file is intentionally focused on pure resolution logic so that
 *   application behavior remains predictable and easy to test.
 *
 * Supported modes:
 * - light
 * - dark
 * - system
 *
 * Resolution flow:
 * - configured mode provides the package default
 * - persisted mode may override the configured mode
 * - when the preferred mode is "system", the final resolved mode is derived
 *   from the user's operating-system/browser preference
 *
 * @package   AuthKit
 * @author    Michael Erastus
 * @license   MIT
 */

import { getConfigValue } from '../../core/config.js';
import { isFunction, normalizeString } from '../../core/helpers.js';


/**
 * Supported appearance modes.
 *
 * @type {string[]}
 */
export const SUPPORTED_MODES = ['light', 'dark', 'system'];


/**
 * Determine whether a given value is a supported appearance mode.
 *
 * @param {*} mode
 * @returns {boolean}
 */
export function isSupportedMode(mode) {
    const normalizedMode = normalizeString(mode, '');

    return SUPPORTED_MODES.includes(normalizedMode);
}


/**
 * Normalize an appearance mode into a supported value.
 *
 * Falls back to the provided fallback when the value is invalid.
 *
 * @param {*} mode
 * @param {string} fallback
 * @returns {string}
 */
export function normalizeMode(mode, fallback = 'system') {
    const normalizedMode = normalizeString(mode, fallback);

    if (isSupportedMode(normalizedMode)) {
        return normalizedMode;
    }

    return isSupportedMode(fallback) ? fallback : 'system';
}


/**
 * Resolve the configured default appearance mode from runtime configuration.
 *
 * Configuration path:
 * - ui.mode
 *
 * @returns {string}
 */
export function getConfiguredMode() {
    const configuredMode = getConfigValue('ui.mode', 'system');

    return normalizeMode(configuredMode, 'system');
}


/**
 * Determine whether the current browser environment supports
 * prefers-color-scheme media queries.
 *
 * @returns {boolean}
 */
export function supportsSystemColorScheme() {
    return (
        typeof window !== 'undefined' &&
        isFunction(window.matchMedia)
    );
}


/**
 * Resolve the current system/browser preferred appearance mode.
 *
 * When unavailable, this falls back to light mode.
 *
 * @returns {string}
 */
export function getSystemMode() {
    if (!supportsSystemColorScheme()) {
        return 'light';
    }

    try {
        return window.matchMedia('(prefers-color-scheme: dark)').matches
            ? 'dark'
            : 'light';
    } catch (error) {
        return 'light';
    }
}


/**
 * Resolve the preferred appearance mode.
 *
 * The preferred mode represents the user's intended mode choice:
 * - a persisted value, when provided and valid
 * - otherwise the configured package mode
 *
 * This value may still be "system".
 *
 * @param {*} persistedMode
 * @returns {string}
 */
export function getPreferredMode(persistedMode = null) {
    const configuredMode = getConfiguredMode();

    if (isSupportedMode(persistedMode)) {
        return normalizeMode(persistedMode, configuredMode);
    }

    return configuredMode;
}


/**
 * Resolve the final applied appearance mode.
 *
 * Rules:
 * - light stays light
 * - dark stays dark
 * - system resolves to the current browser/system preference
 *
 * @param {*} preferredMode
 * @returns {string}
 */
export function getResolvedMode(preferredMode = null) {
    const normalizedPreferredMode = getPreferredMode(preferredMode);

    if (normalizedPreferredMode === 'system') {
        return getSystemMode();
    }

    return normalizeMode(normalizedPreferredMode, 'light');
}


/**
 * Resolve the full theme mode state payload.
 *
 * This helper returns the complete state needed by the theme runtime:
 * - configuredMode: package-configured default mode
 * - preferredMode: user-facing preferred mode (may be system)
 * - resolvedMode: actual applied mode (light or dark)
 * - systemMode: currently detected system mode
 *
 * @param {*} persistedMode
 * @returns {{
 *   configuredMode: string,
 *   preferredMode: string,
 *   resolvedMode: string,
 *   systemMode: string
 * }}
 */
export function resolveModeState(persistedMode = null) {
    const configuredMode = getConfiguredMode();
    const preferredMode = getPreferredMode(persistedMode);
    const systemMode = getSystemMode();
    const resolvedMode = preferredMode === 'system'
        ? systemMode
        : normalizeMode(preferredMode, 'light');

    return {
        configuredMode,
        preferredMode,
        resolvedMode,
        systemMode,
    };
}