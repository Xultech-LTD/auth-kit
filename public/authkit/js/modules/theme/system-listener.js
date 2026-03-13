/**
 * AuthKit
 * -----------------------------------------------------------------------------
 * File: js/modules/theme/system-listener.js
 * Author: Michael Erastus
 * Package: AuthKit
 *
 * Description:
 * -----------------------------------------------------------------------------
 * System appearance listener utilities for the AuthKit browser runtime.
 *
 * This file is responsible for listening to operating-system/browser appearance
 * preference changes and notifying the theme runtime when those changes matter.
 *
 * Responsibilities:
 * - Detect whether system color-scheme change listeners are supported.
 * - Resolve the prefers-color-scheme media query list safely.
 * - Bind a listener for system appearance changes.
 * - Notify the theme runtime only when the preferred mode is "system".
 *
 * Design notes:
 * - This file does not persist theme mode values.
 * - This file does not write theme state to the DOM directly.
 * - This file does not decide how theme changes are applied; it only reports
 *   that a relevant system appearance change occurred.
 * - This file supports both modern and older browser media-query listener APIs.
 *
 * Expected usage:
 * - The theme module passes:
 *   - a callback that returns the current preferred mode
 *   - a callback that handles a relevant system mode change
 *
 * @package   AuthKit
 * @author    Michael Erastus
 * @license   MIT
 */

import { isFunction } from '../../core/helpers.js';
import { getSystemMode, normalizeMode, supportsSystemColorScheme } from './resolve-mode.js';


/**
 * Default media query used to observe system appearance changes.
 *
 * @type {string}
 */
export const SYSTEM_COLOR_SCHEME_QUERY = '(prefers-color-scheme: dark)';


/**
 * Resolve the MediaQueryList used for system color-scheme detection.
 *
 * @returns {MediaQueryList|null}
 */
export function getSystemColorSchemeMediaQuery() {
    if (!supportsSystemColorScheme()) {
        return null;
    }

    try {
        return window.matchMedia(SYSTEM_COLOR_SCHEME_QUERY);
    } catch (error) {
        return null;
    }
}


/**
 * Determine whether the provided MediaQueryList supports the modern
 * addEventListener/removeEventListener API.
 *
 * @param {MediaQueryList|null} mediaQueryList
 * @returns {boolean}
 */
export function supportsModernMediaQueryListeners(mediaQueryList) {
    return Boolean(
        mediaQueryList &&
        isFunction(mediaQueryList.addEventListener) &&
        isFunction(mediaQueryList.removeEventListener)
    );
}


/**
 * Determine whether the provided MediaQueryList supports the legacy
 * addListener/removeListener API.
 *
 * @param {MediaQueryList|null} mediaQueryList
 * @returns {boolean}
 */
export function supportsLegacyMediaQueryListeners(mediaQueryList) {
    return Boolean(
        mediaQueryList &&
        isFunction(mediaQueryList.addListener) &&
        isFunction(mediaQueryList.removeListener)
    );
}


/**
 * Bind a low-level listener to the system color-scheme media query.
 *
 * This helper abstracts browser differences between:
 * - addEventListener('change', ...)
 * - addListener(...)
 *
 * @param {Function} listener
 * @returns {Function}
 */
export function bindSystemColorSchemeListener(listener) {
    if (!isFunction(listener)) {
        return () => {};
    }

    const mediaQueryList = getSystemColorSchemeMediaQuery();

    if (!mediaQueryList) {
        return () => {};
    }

    if (supportsModernMediaQueryListeners(mediaQueryList)) {
        mediaQueryList.addEventListener('change', listener);

        return () => {
            mediaQueryList.removeEventListener('change', listener);
        };
    }

    if (supportsLegacyMediaQueryListeners(mediaQueryList)) {
        mediaQueryList.addListener(listener);

        return () => {
            mediaQueryList.removeListener(listener);
        };
    }

    return () => {};
}


/**
 * Bind a high-level system appearance listener for the AuthKit theme runtime.
 *
 * The supplied getPreferredMode callback is used to determine whether the
 * current user preference is "system". Only in that case will the onChange
 * callback be invoked when the operating-system/browser preference changes.
 *
 * Callback payload shape:
 * - preferredMode: current preferred mode
 * - resolvedMode: current resolved mode after the system change
 * - systemMode: current system/browser mode
 * - event: original media-query event when available
 *
 * @param {Function} getPreferredMode
 * @param {Function} onChange
 * @returns {Function}
 */
export function bindSystemModeListener(getPreferredMode, onChange) {
    if (!isFunction(getPreferredMode) || !isFunction(onChange)) {
        return () => {};
    }

    return bindSystemColorSchemeListener((event) => {
        const preferredMode = normalizeMode(getPreferredMode(), 'system');

        if (preferredMode !== 'system') {
            return;
        }

        const systemMode = getSystemMode();

        onChange({
            preferredMode,
            resolvedMode: systemMode,
            systemMode,
            event,
        });
    });
}