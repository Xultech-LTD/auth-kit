/**
 * AuthKit
 * -----------------------------------------------------------------------------
 * File: js/modules/theme/toggles.js
 * Author: Michael Erastus
 * Package: AuthKit
 *
 * Description:
 * -----------------------------------------------------------------------------
 * Theme toggle utilities for the AuthKit browser runtime.
 *
 * This file is responsible for discovering, synchronizing, and binding the
 * packaged AuthKit theme toggle controls.
 *
 * Responsibilities:
 * - Resolve configured theme toggle controls from the DOM.
 * - Read mode values from toggle controls.
 * - Synchronize toggle UI state with the active preferred mode.
 * - Bind click/change handlers for:
 *   - explicit mode option buttons
 *   - dropdown selects
 *   - cycle buttons
 * - Delegate mode changes through a caller-provided callback.
 *
 * Design notes:
 * - This file does not resolve persisted values directly.
 * - This file does not determine system/browser preference logic directly.
 * - This file does not write final mode state to the document root.
 * - This file remains focused on DOM toggle behavior only.
 *
 * Supported toggle patterns:
 * - option buttons:
 *   data-authkit-theme-toggle="light|dark|system"
 * - select dropdown:
 *   data-authkit-theme-toggle-select="1"
 * - cycle button:
 *   data-authkit-theme-toggle-cycle="1"
 *
 * @package   AuthKit
 * @author    Michael Erastus
 * @license   MIT
 */

import { getConfigValue } from '../../core/config.js';
import { getDataAttribute, getThemeToggleCycleButtons, getThemeToggleOptions, getThemeToggleSelects, listen } from '../../core/dom.js';
import { isFunction, normalizeString } from '../../core/helpers.js';
import { normalizeMode } from './resolve-mode.js';


/**
 * Resolve the configured theme toggle option attribute name.
 *
 * Configuration path:
 * - ui.toggle.attribute
 *
 * @returns {string}
 */
export function getToggleAttribute() {
    return normalizeString(
        getConfigValue('ui.toggle.attribute', 'data-authkit-theme-toggle'),
        'data-authkit-theme-toggle'
    );
}


/**
 * Resolve whether the packaged toggle should allow the "system" option.
 *
 * Configuration path:
 * - ui.toggle.allowSystem
 *
 * @returns {boolean}
 */
export function allowsSystemMode() {
    return Boolean(
        getConfigValue('ui.toggle.allowSystem', true)
    );
}


/**
 * Resolve the ordered set of available toggle modes.
 *
 * @returns {string[]}
 */
export function getAvailableModes() {
    return allowsSystemMode()
        ? ['light', 'dark', 'system']
        : ['light', 'dark'];
}


/**
 * Resolve all explicit theme toggle option buttons/elements.
 *
 * @returns {HTMLElement[]}
 */
export function resolveToggleOptions() {
    return getThemeToggleOptions(getToggleAttribute());
}


/**
 * Resolve all theme toggle select controls.
 *
 * @returns {HTMLSelectElement[]}
 */
export function resolveToggleSelects() {
    return getThemeToggleSelects();
}


/**
 * Resolve all theme toggle cycle buttons.
 *
 * @returns {HTMLButtonElement[]}
 */
export function resolveToggleCycleButtons() {
    return getThemeToggleCycleButtons();
}


/**
 * Resolve all known packaged theme toggle controls.
 *
 * @returns {{
 *   options: HTMLElement[],
 *   selects: HTMLSelectElement[],
 *   cycleButtons: HTMLButtonElement[]
 * }}
 */
export function getToggleControls() {
    return {
        options: resolveToggleOptions(),
        selects: resolveToggleSelects(),
        cycleButtons: resolveToggleCycleButtons(),
    };
}


/**
 * Read the target mode from an explicit toggle option element.
 *
 * @param {Element|null} element
 * @returns {string|null}
 */
export function readToggleOptionMode(element) {
    const mode = getDataAttribute(element, getToggleAttribute(), null);

    if (mode === null) {
        return null;
    }

    const normalizedMode = normalizeMode(mode, '');

    return normalizedMode !== '' ? normalizedMode : null;
}


/**
 * Resolve the next mode for a cycle toggle button.
 *
 * Example cycles:
 * - with system: light -> dark -> system -> light
 * - without system: light -> dark -> light
 *
 * @param {*} currentMode
 * @returns {string}
 */
export function getNextToggleMode(currentMode) {
    const modes = getAvailableModes();
    const normalizedCurrentMode = normalizeMode(currentMode, modes[0] ?? 'light');
    const currentIndex = modes.indexOf(normalizedCurrentMode);

    if (currentIndex === -1) {
        return modes[0] ?? 'light';
    }

    const nextIndex = (currentIndex + 1) % modes.length;

    return modes[nextIndex] ?? 'light';
}


/**
 * Synchronize explicit option toggle state with the current preferred mode.
 *
 * Applied attributes:
 * - aria-pressed
 * - data-authkit-theme-toggle-active
 *
 * @param {*} preferredMode
 * @param {HTMLElement[]} [options]
 * @returns {void}
 */
export function syncToggleOptions(preferredMode, options = resolveToggleOptions()) {
    const normalizedPreferredMode = normalizeMode(preferredMode, 'system');

    options.forEach((element) => {
        const optionMode = readToggleOptionMode(element);
        const isActive = optionMode === normalizedPreferredMode;

        element.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        element.setAttribute('data-authkit-theme-toggle-active', isActive ? 'true' : 'false');
    });
}


/**
 * Synchronize select toggle controls with the current preferred mode.
 *
 * @param {*} preferredMode
 * @param {HTMLSelectElement[]} [selects]
 * @returns {void}
 */
export function syncToggleSelects(preferredMode, selects = resolveToggleSelects()) {
    const normalizedPreferredMode = normalizeMode(preferredMode, 'system');

    selects.forEach((select) => {
        select.value = normalizedPreferredMode;
    });
}


/**
 * Synchronize cycle buttons with the current preferred mode.
 *
 * Applied attributes:
 * - data-authkit-theme-toggle-current
 * - data-authkit-theme-toggle-next
 * - aria-label
 * - title
 *
 * @param {*} preferredMode
 * @param {HTMLButtonElement[]} [cycleButtons]
 * @returns {void}
 */
export function syncToggleCycleButtons(preferredMode, cycleButtons = resolveToggleCycleButtons()) {
    const normalizedPreferredMode = normalizeMode(preferredMode, 'system');
    const nextMode = getNextToggleMode(normalizedPreferredMode);

    cycleButtons.forEach((button) => {
        button.setAttribute('data-authkit-theme-toggle-current', normalizedPreferredMode);
        button.setAttribute('data-authkit-theme-toggle-next', nextMode);
        button.setAttribute(
            'aria-label',
            `Toggle appearance mode (current: ${normalizedPreferredMode}, next: ${nextMode})`
        );
        button.setAttribute(
            'title',
            `Current: ${normalizedPreferredMode}. Next: ${nextMode}.`
        );
    });
}


/**
 * Synchronize all packaged theme toggle controls with the current preferred mode.
 *
 * @param {*} preferredMode
 * @returns {void}
 */
export function syncToggleState(preferredMode) {
    const controls = getToggleControls();

    syncToggleOptions(preferredMode, controls.options);
    syncToggleSelects(preferredMode, controls.selects);
    syncToggleCycleButtons(preferredMode, controls.cycleButtons);
}


/**
 * Bind explicit mode option toggle controls.
 *
 * The supplied callback receives the requested preferred mode.
 *
 * @param {Function} onChange
 * @param {HTMLElement[]} [options]
 * @returns {Function[]}
 */
export function bindToggleOptions(onChange, options = resolveToggleOptions()) {
    if (!isFunction(onChange)) {
        return [];
    }

    return options.map((element) => {
        return listen(element, 'click', (event) => {
            event.preventDefault();

            const mode = readToggleOptionMode(element);

            if (mode === null) {
                return;
            }

            onChange(mode, {
                source: 'option',
                element,
                event,
            });
        });
    });
}


/**
 * Bind select toggle controls.
 *
 * The supplied callback receives the requested preferred mode.
 *
 * @param {Function} onChange
 * @param {HTMLSelectElement[]} [selects]
 * @returns {Function[]}
 */
export function bindToggleSelects(onChange, selects = resolveToggleSelects()) {
    if (!isFunction(onChange)) {
        return [];
    }

    return selects.map((select) => {
        return listen(select, 'change', (event) => {
            const mode = normalizeMode(select.value, '');

            if (mode === '') {
                return;
            }

            onChange(mode, {
                source: 'select',
                element: select,
                event,
            });
        });
    });
}


/**
 * Bind cycle toggle buttons.
 *
 * The supplied callback receives the requested next preferred mode.
 *
 * @param {Function} onChange
 * @param {Function} getCurrentMode
 * @param {HTMLButtonElement[]} [cycleButtons]
 * @returns {Function[]}
 */
export function bindToggleCycleButtons(
    onChange,
    getCurrentMode,
    cycleButtons = resolveToggleCycleButtons()
) {
    if (!isFunction(onChange) || !isFunction(getCurrentMode)) {
        return [];
    }

    return cycleButtons.map((button) => {
        return listen(button, 'click', (event) => {
            event.preventDefault();

            const currentMode = getCurrentMode();
            const nextMode = getNextToggleMode(currentMode);

            onChange(nextMode, {
                source: 'cycle',
                element: button,
                event,
                currentMode: normalizeMode(currentMode, 'system'),
                nextMode,
            });
        });
    });
}


/**
 * Bind all packaged theme toggle controls.
 *
 * The supplied callback receives:
 * - the requested preferred mode
 * - metadata describing the interaction source
 *
 * Options:
 * - getCurrentMode: callback used by cycle buttons to determine the current mode
 *
 * @param {Function} onChange
 * @param {Object} [options={}]
 * @param {Function|null} [options.getCurrentMode=null]
 * @returns {Function}
 */
export function bindThemeToggles(onChange, options = {}) {
    if (!isFunction(onChange)) {
        return () => {};
    }

    const getCurrentMode = isFunction(options.getCurrentMode)
        ? options.getCurrentMode
        : () => 'system';

    const cleanups = [
        ...bindToggleOptions(onChange),
        ...bindToggleSelects(onChange),
        ...bindToggleCycleButtons(onChange, getCurrentMode),
    ];

    return () => {
        cleanups.forEach((cleanup) => {
            if (isFunction(cleanup)) {
                cleanup();
            }
        });
    };
}