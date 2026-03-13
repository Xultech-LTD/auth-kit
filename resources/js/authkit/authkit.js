/**
 * AuthKit
 * -----------------------------------------------------------------------------
 * File: js/authkit.js
 * Author: Michael Erastus
 * Package: AuthKit
 *
 * Description:
 * -----------------------------------------------------------------------------
 * Public browser entrypoint for the AuthKit client runtime.
 *
 * This file is responsible for bootstrapping the AuthKit browser runtime from a
 * single public entry so packaged assets can expose one stable client script.
 *
 * Responsibilities:
 * - Wait for DOM readiness before booting the runtime.
 * - Resolve the merged AuthKit browser configuration.
 * - Resolve the built-in shared runtime module registry.
 * - Resolve the built-in page runtime module registry.
 * - Boot the central AuthKit runtime once.
 * - Expose a small public boot API for diagnostics, testing, or manual boot.
 *
 * Design notes:
 * - This file does not contain page-specific behavior.
 * - This file does not contain theme logic.
 * - This file does not contain forms submission logic.
 * - This file remains a thin orchestration layer over the runtime core.
 *
 * Public global shape:
 * - window.AuthKit.config
 * - window.AuthKit.runtime
 *
 * @package   AuthKit
 * @author    Michael Erastus
 * @license   MIT
 */

import { getConfig } from './core/config.js';
import { onDomReady } from './core/dom.js';
import { bootRuntime, getRuntimeState, hasBooted } from './core/runtime.js';
import { getModuleRegistry } from './registry/modules.js';
import { getPageRegistry } from './registry/pages.js';


/**
 * Boot the AuthKit runtime using the current browser configuration and built-in
 * registries.
 *
 * This helper is safe to call repeatedly because the runtime core is
 * intentionally idempotent.
 *
 * @returns {Object}
 */
export function bootAuthKit() {
    return bootRuntime({
        config: getConfig(),
        moduleRegistry: getModuleRegistry(),
        pageRegistry: getPageRegistry(),
    });
}


/**
 * Resolve a snapshot of the current AuthKit runtime state.
 *
 * @returns {Object}
 */
export function getAuthKitState() {
    return getRuntimeState();
}


/**
 * Determine whether the AuthKit runtime has already booted.
 *
 * @returns {boolean}
 */
export function isAuthKitBooted() {
    return hasBooted();
}


/**
 * Automatically boot AuthKit when the DOM is ready.
 */
onDomReady(() => {
    bootAuthKit();
});