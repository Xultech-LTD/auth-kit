/**
 * AuthKit
 * -----------------------------------------------------------------------------
 * File: js/registry/modules.js
 * Author: Michael Erastus
 * Package: AuthKit
 *
 * Description:
 * -----------------------------------------------------------------------------
 * Shared runtime module registry for the AuthKit browser client.
 *
 * This registry provides the canonical map of bootable shared modules that may
 * be initialized by the AuthKit runtime.
 *
 * Responsibilities:
 * - Register built-in shared runtime modules.
 * - Provide a stable lookup map for runtime boot orchestration.
 * - Keep module registration centralized and easy to extend.
 *
 * Design notes:
 * - Shared modules are runtime-wide features, not page-specific modules.
 * - Modules listed here should expose a `boot(context)` function.
 * - This file should stay declarative and avoid runtime business logic.
 *
 * @package   AuthKit
 * @author    Michael Erastus
 * @license   MIT
 */

import * as themeModule from '../modules/theme/index.js';
import * as formsModule from '../modules/forms/index.js';
import { normalizeString } from '../core/helpers.js';
import * as appShellModule from '../modules/app-shell/index.js';

/**
 * Built-in shared runtime module registry.
 *
 * Keys must align with configuration under:
 * - authkit.javascript.modules.{key}
 *
 * Example:
 * - theme
 * - forms
 *
 * @type {Record<string, Object>}
 */
export const moduleRegistry = Object.freeze({
    theme: themeModule,
    forms: formsModule,
    appShell: appShellModule,
});


/**
 * Resolve the full shared runtime module registry.
 *
 * A shallow clone is returned to avoid accidental external mutation.
 *
 * @returns {Record<string, Object>}
 */
export function getModuleRegistry() {
    return { ...moduleRegistry };
}


/**
 * Resolve a single shared runtime module by key.
 *
 * @param {string} key
 * @returns {Object|null}
 */
export function getModule(key) {
    const normalizedKey = normalizeString(key, '');

    if (normalizedKey === '') {
        return null;
    }

    return moduleRegistry[normalizedKey] ?? null;
}


/**
 * Determine whether a shared runtime module exists in the registry.
 *
 * @param {string} key
 * @returns {boolean}
 */
export function hasModule(key) {
    return getModule(key) !== null;
}