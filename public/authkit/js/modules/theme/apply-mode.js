/**
 * AuthKit
 * -----------------------------------------------------------------------------
 * File: apply-mode.js
 * Author: Michael Erastus
 * Package: AuthKit
 *
 * Description:
 * -----------------------------------------------------------------------------
 * Theme mode application utilities for the AuthKit browser runtime.
 *
 * This file is responsible for applying resolved theme mode state to the
 * current document.
 *
 * Responsibilities:
 * - Apply the preferred appearance mode to the document root.
 * - Apply the resolved appearance mode to the document root.
 * - Apply a complete theme mode state payload to the document root.
 * - Keep DOM mutation concerns isolated from pure mode resolution logic.
 *
 * Design notes:
 * - This file writes only to the DOM.
 * - This file does not determine persisted mode values.
 * - This file does not resolve system/browser preference logic directly.
 * - This file should remain small and predictable so it can be reused by
 *   the theme module, toggle handlers, and system preference listeners.
 *
 * Root attributes written:
 * - data-authkit-mode-preference
 * - data-authkit-mode-resolved
 * - data-authkit-mode
 *
 * @package   AuthKit
 * @author    Michael Erastus
 * @license   MIT
 */

import { getDocumentRoot } from '../../core/dom.js';
import { isObject, normalizeString } from '../../core/helpers.js';
import { normalizeMode } from './resolve-mode.js';


/**
 * Resolve the active document root used for theme state application.
 *
 * @returns {HTMLElement|null}
 */
export function getThemeRoot() {
    return getDocumentRoot();
}


/**
 * Apply the preferred appearance mode to the document root.
 *
 * The preferred mode represents the user's intended selection and may be:
 * - light
 * - dark
 * - system
 *
 * @param {*} preferredMode
 * @returns {string|null}
 */
export function applyPreferredMode(preferredMode) {
    const root = getThemeRoot();

    if (!root) {
        return null;
    }

    const normalizedPreferredMode = normalizeMode(preferredMode, 'system');

    root.setAttribute(
        'data-authkit-mode-preference',
        normalizedPreferredMode
    );

    return normalizedPreferredMode;
}


/**
 * Apply the resolved appearance mode to the document root.
 *
 * The resolved mode is the actual mode being rendered and must always be:
 * - light
 * - dark
 *
 * If an invalid value or "system" is provided, this falls back to light.
 *
 * @param {*} resolvedMode
 * @returns {string|null}
 */
export function applyResolvedMode(resolvedMode) {
    const root = getThemeRoot();

    if (!root) {
        return null;
    }

    const normalizedResolvedMode = normalizeString(resolvedMode, 'light') === 'system'
        ? 'light'
        : normalizeMode(resolvedMode, 'light');

    root.setAttribute('data-authkit-mode-resolved', normalizedResolvedMode);
    root.setAttribute('data-authkit-mode', normalizedResolvedMode);

    return normalizedResolvedMode;
}


/**
 * Apply a full AuthKit mode state payload to the document root.
 *
 * Expected state shape:
 * - configuredMode
 * - preferredMode
 * - resolvedMode
 * - systemMode
 *
 * Only preferredMode and resolvedMode are written to the DOM.
 * Other properties are preserved in the returned normalized payload.
 *
 * @param {Object|null} modeState
 * @returns {Object|null}
 */
export function applyModeState(modeState = null) {
    if (!isObject(modeState)) {
        return null;
    }

    const preferredMode = applyPreferredMode(modeState.preferredMode);
    const resolvedMode = applyResolvedMode(modeState.resolvedMode);

    return {
        ...modeState,
        preferredMode: preferredMode ?? normalizeMode(modeState.preferredMode, 'system'),
        resolvedMode: resolvedMode ?? 'light',
    };
}


/**
 * Read the currently applied preferred appearance mode from the document root.
 *
 * @returns {string|null}
 */
export function readAppliedPreferredMode() {
    const root = getThemeRoot();

    if (!root) {
        return null;
    }

    return normalizeString(
        root.getAttribute('data-authkit-mode-preference'),
        null
    );
}


/**
 * Read the currently applied resolved appearance mode from the document root.
 *
 * @returns {string|null}
 */
export function readAppliedResolvedMode() {
    const root = getThemeRoot();

    if (!root) {
        return null;
    }

    return normalizeString(
        root.getAttribute('data-authkit-mode-resolved'),
        null
    );
}