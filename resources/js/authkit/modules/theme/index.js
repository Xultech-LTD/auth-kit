/**
 * AuthKit
 * -----------------------------------------------------------------------------
 * File: js/modules/theme/index.js
 * Author: Michael Erastus
 * Package: AuthKit
 *
 * Description:
 * -----------------------------------------------------------------------------
 * Theme module entry point for the AuthKit browser runtime.
 *
 * This file orchestrates the complete theme system lifecycle:
 * - resolving persisted theme preference
 * - resolving configured and system theme modes
 * - applying theme state to the document
 * - binding theme toggle controls
 * - binding system color-scheme listeners
 * - dispatching theme lifecycle events
 *
 * Responsibilities:
 * - Resolve the initial theme mode state.
 * - Apply theme state to the document root.
 * - Persist user-selected theme modes when enabled.
 * - Synchronize UI toggle controls.
 * - React to operating-system color scheme changes.
 * - Emit stable runtime events for extensions.
 *
 * Design notes:
 * - This file orchestrates the theme system but does not implement the
 *   underlying resolution, persistence, or DOM mutation logic directly.
 * - Each concern is delegated to a specialized helper module.
 *
 * Runtime events emitted:
 * - theme_ready
 * - theme_changed
 *
 * @package   AuthKit
 * @author    Michael Erastus
 * @license   MIT
 */

import {dispatchEvent} from '../../core/events.js';

import {resolveModeState} from './resolve-mode.js';
import {applyModeState} from './apply-mode.js';

import {persistMode, readPersistedMode} from './persistence.js';

import {bindThemeToggles, syncToggleState} from './toggles.js';

import {bindSystemModeListener} from './system-listener.js';
import {isObject} from "../../core/helpers.js";


/**
 * Internal theme module state.
 *
 * @type {{
 *   preferredMode: string|null,
 *   resolvedMode: string|null,
 *   systemMode: string|null
 * }}
 */
const themeState = {
    preferredMode: null,
    resolvedMode: null,
    systemMode: null,
};


/**
 * Resolve the current preferred appearance mode.
 *
 * @returns {string|null}
 */
export function getPreferredMode() {
    return themeState.preferredMode;
}


/**
 * Resolve the currently applied appearance mode.
 *
 * @returns {string|null}
 */
export function getResolvedMode() {
    return themeState.resolvedMode;
}


/**
 * Update internal theme state from a resolved mode state payload.
 *
 * @param {Object|null} modeState
 * @returns {void}
 */
export function updateThemeState(modeState) {
    if (!modeState || !isObject(modeState)) {
        return;
    }

    themeState.preferredMode = modeState.preferredMode ?? null;
    themeState.resolvedMode = modeState.resolvedMode ?? null;
    themeState.systemMode = modeState.systemMode ?? null;
}


/**
 * Apply a new preferred appearance mode.
 *
 * Responsibilities:
 * - resolve new mode state
 * - apply DOM updates
 * - persist the preference
 * - synchronize UI toggles
 * - emit theme change events
 *
 * @param {*} preferredMode
 * @param {Object} [meta={}]
 * @returns {Object|null}
 */
export function setPreferredMode(preferredMode, meta = {}) {
    const modeState = resolveModeState(preferredMode);

    const appliedState = applyModeState(modeState);

    updateThemeState(appliedState);

    persistMode(themeState.preferredMode);

    syncToggleState(themeState.preferredMode);

    dispatchEvent('theme_changed', {
        preferredMode: themeState.preferredMode,
        resolvedMode: themeState.resolvedMode,
        systemMode: themeState.systemMode,
        meta,
    });

    return appliedState;
}


/**
 * Initialize theme toggle bindings.
 *
 * @returns {Function}
 */
export function initThemeToggles() {
    return bindThemeToggles(
        (mode, meta) => {
            setPreferredMode(mode, meta);
        },
        {
            getCurrentMode: () => getPreferredMode(),
        }
    );
}


/**
 * Initialize the system color-scheme listener.
 *
 * @returns {Function}
 */
export function initSystemListener() {
    return bindSystemModeListener(
        () => getPreferredMode(),
        ({ systemMode }) => {
            const modeState = resolveModeState(getPreferredMode());

            const appliedState = applyModeState(modeState);

            updateThemeState(appliedState);

            syncToggleState(themeState.preferredMode);

            dispatchEvent('theme_changed', {
                preferredMode: themeState.preferredMode,
                resolvedMode: themeState.resolvedMode,
                systemMode,
                meta: { source: 'system' },
            });
        }
    );
}


/**
 * Boot the AuthKit theme module.
 *
 * Expected module contract:
 * - boot(context) => any
 *
 * @param {Object} context
 * @returns {Object}
 */
export function boot(context) {
    const persistedMode = readPersistedMode();

    const modeState = resolveModeState(persistedMode);

    const appliedState = applyModeState(modeState);

    updateThemeState(appliedState);

    syncToggleState(themeState.preferredMode);

    initThemeToggles();
    initSystemListener();

    dispatchEvent('theme_ready', {
        preferredMode: themeState.preferredMode,
        resolvedMode: themeState.resolvedMode,
        systemMode: themeState.systemMode,
    });

    return {
        getPreferredMode,
        getResolvedMode,
        setPreferredMode,
    };
}