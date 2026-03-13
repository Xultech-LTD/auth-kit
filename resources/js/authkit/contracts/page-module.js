/**
 * AuthKit
 * -----------------------------------------------------------------------------
 * File: js/contracts/page-module.js
 * Author: Michael Erastus
 * Package: AuthKit
 *
 * Description:
 * -----------------------------------------------------------------------------
 * Runtime contract helpers for AuthKit page modules.
 *
 * Because AuthKit is implemented in plain JavaScript, page-module contracts are
 * enforced through small runtime guards rather than compile-time interfaces.
 *
 * Responsibilities:
 * - Determine whether a value is a valid AuthKit page module definition.
 * - Resolve the boot function from a page module safely.
 * - Provide a normalized contract check used by registries and tests.
 *
 * Expected page module contract:
 * - module.boot(context) => any
 *
 * Design notes:
 * - This file does not boot modules.
 * - This file does not resolve page configuration.
 * - This file exists only to validate and describe the expected page-module
 *   shape used by the AuthKit runtime.
 *
 * @package   AuthKit
 * @author    Michael Erastus
 * @license   MIT
 */

import { isFunction, isObject } from '../core/helpers.js';


/**
 * Determine whether the supplied value exposes a valid AuthKit page-module
 * boot function.
 *
 * Expected contract:
 * - { boot: Function }
 *
 * @param {*} value
 * @returns {boolean}
 */
export function isPageModule(value) {
    return isObject(value) && isFunction(value.boot);
}


/**
 * Resolve the boot function from a page module safely.
 *
 * Returns null when the supplied value does not satisfy the page-module
 * contract.
 *
 * @param {*} value
 * @returns {Function|null}
 */
export function getPageModuleBoot(value) {
    if (!isPageModule(value)) {
        return null;
    }

    return value.boot;
}


/**
 * Validate a page module and throw a descriptive error when invalid.
 *
 * This helper is useful for tests, diagnostics, or future stricter registry
 * validation.
 *
 * @param {*} value
 * @param {string} [label='page module']
 * @returns {Object}
 */
export function assertPageModule(value, label = 'page module') {
    if (!isPageModule(value)) {
        throw new Error(`AuthKit ${label} must expose a boot(context) function.`);
    }

    return value;
}