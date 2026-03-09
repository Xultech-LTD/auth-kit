/**
 * AuthKit
 * -----------------------------------------------------------------------------
 * File: runtime-context.js
 * Author: Michael Erastus
 * Package: AuthKit
 *
 * Description:
 * -----------------------------------------------------------------------------
 * Runtime contract helpers for the AuthKit runtime context object.
 *
 * Because AuthKit is implemented in plain JavaScript, runtime-context contracts
 * are enforced through small runtime guards rather than compile-time interfaces.
 *
 * Responsibilities:
 * - Determine whether a value looks like a valid AuthKit runtime context.
 * - Validate the minimum context shape expected by modules and page scripts.
 * - Provide safe access to a normalized runtime context contract.
 *
 * Expected runtime context shape:
 * - {
 *     root: HTMLElement|null,
 *     pageElement: HTMLElement|null,
 *     page: Object,
 *     config: Object,
 *     moduleRegistry: Object,
 *     pageRegistry: Object,
 *     getRuntime: Function,
 *     getState: Function,
 *     emit: Function
 *   }
 *
 * Design notes:
 * - This file does not create runtime contexts.
 * - This file does not mutate runtime state.
 * - This file exists only to validate and describe the expected runtime-context
 *   shape used by the AuthKit runtime.
 *
 * @package   AuthKit
 * @author    Michael Erastus
 * @license   MIT
 */

import { isFunction, isObject } from '../core/helpers.js';


/**
 * Determine whether the supplied value satisfies the minimum AuthKit runtime
 * context contract.
 *
 * Required members:
 * - page
 * - config
 * - moduleRegistry
 * - pageRegistry
 * - getRuntime()
 * - getState()
 * - emit()
 *
 * @param {*} value
 * @returns {boolean}
 */
export function isRuntimeContext(value) {
    return (
        isObject(value) &&
        isObject(value.page) &&
        isObject(value.config) &&
        isObject(value.moduleRegistry) &&
        isObject(value.pageRegistry) &&
        isFunction(value.getRuntime) &&
        isFunction(value.getState) &&
        isFunction(value.emit)
    );
}


/**
 * Validate a runtime context and throw a descriptive error when invalid.
 *
 * @param {*} value
 * @param {string} [label='runtime context']
 * @returns {Object}
 */
export function assertRuntimeContext(value, label = 'runtime context') {
    if (!isRuntimeContext(value)) {
        throw new Error(`AuthKit ${label} must expose the required runtime context shape.`);
    }

    return value;
}