/**
 * AuthKit
 * -----------------------------------------------------------------------------
 * File: js/core/helpers.js
 * Author: Michael Erastus
 * Package: AuthKit
 *
 * Description:
 * -----------------------------------------------------------------------------
 * Shared utility helpers used across the AuthKit browser runtime.
 *
 * These helpers intentionally remain small, dependency-free, and framework-
 * agnostic so that the runtime core remains predictable and easy to maintain.
 *
 * Responsibilities:
 * - Provide safe type guards.
 * - Provide small normalization helpers.
 * - Provide defensive utilities used by modules.
 *
 * Design notes:
 * - These helpers must never depend on DOM APIs.
 * - These helpers must never depend on runtime modules.
 * - This file should remain extremely stable across releases.
 *
 * @package   AuthKit
 * @author    Michael Erastus
 * @license   MIT
 */


/**
 * Determine whether a value is a plain object.
 *
 * @param {*} value
 * @returns {boolean}
 */
export function isObject(value) {
    return value !== null && typeof value === 'object' && !Array.isArray(value);
}


/**
 * Determine whether a value is a function.
 *
 * @param {*} value
 * @returns {boolean}
 */
export function isFunction(value) {
    return typeof value === 'function';
}


/**
 * Determine whether a value is a string.
 *
 * @param {*} value
 * @returns {boolean}
 */
export function isString(value) {
    return typeof value === 'string';
}


/**
 * Determine whether a value is undefined.
 *
 * @param {*} value
 * @returns {boolean}
 */
export function isUndefined(value) {
    return typeof value === 'undefined';
}


/**
 * Determine whether a value is a boolean.
 *
 * @param {*} value
 * @returns {boolean}
 */
export function isBoolean(value) {
    return typeof value === 'boolean';
}


/**
 * Determine whether an object owns a property directly.
 *
 * @param {Object} object
 * @param {string} key
 * @returns {boolean}
 */
export function hasOwn(object, key) {
    if (!isObject(object) && typeof object !== 'function') {
        return false;
    }

    return Object.prototype.hasOwnProperty.call(object, key);
}


/**
 * Convert a value into an array.
 *
 * If the value is already an array, it is returned unchanged.
 * If the value is null or undefined, an empty array is returned.
 *
 * @param {*} value
 * @returns {Array}
 */
export function toArray(value) {
    if (Array.isArray(value)) {
        return value;
    }

    if (value === null || value === undefined) {
        return [];
    }

    return [value];
}


/**
 * Convert a value into a boolean using defensive normalization.
 *
 * Supported truthy string values:
 * - "true"
 * - "1"
 * - "yes"
 * - "on"
 *
 * Supported falsy string values:
 * - "false"
 * - "0"
 * - "no"
 * - "off"
 *
 * @param {*} value
 * @returns {boolean}
 */
export function toBoolean(value) {
    if (isBoolean(value)) {
        return value;
    }

    if (typeof value === 'number') {
        return value !== 0;
    }

    if (isString(value)) {
        const normalized = value.trim().toLowerCase();

        if (['true', '1', 'yes', 'on'].includes(normalized)) {
            return true;
        }

        if (['false', '0', 'no', 'off', ''].includes(normalized)) {
            return false;
        }
    }

    return Boolean(value);
}


/**
 * Normalize a string value.
 *
 * Trims surrounding whitespace and ensures a string result.
 *
 * @param {*} value
 * @param {string} fallback
 * @returns {string}
 */
export function normalizeString(value, fallback = '') {
    if (!isString(value)) {
        return fallback;
    }

    const trimmed = value.trim();

    return trimmed !== '' ? trimmed : fallback;
}


/**
 * Safely access a nested object path.
 *
 * Similar to Laravel's data_get helper.
 *
 * @param {Object} object
 * @param {string} path
 * @param {*} defaultValue
 * @returns {*}
 */
export function dataGet(object, path, defaultValue = null) {
    if (!isObject(object)) {
        return defaultValue;
    }

    if (!isString(path) || path === '') {
        return defaultValue;
    }

    const segments = path.split('.');

    let current = object;

    for (const segment of segments) {
        if (
            (!isObject(current) && typeof current !== 'function') ||
            !hasOwn(current, segment)
        ) {
            return defaultValue;
        }

        current = current[segment];
    }

    return current;
}


/**
 * Create a shallow cloned plain object.
 *
 * Returns an empty object when the supplied value is not a plain object.
 *
 * @param {*} value
 * @returns {Object}
 */
export function cloneObject(value) {
    return isObject(value) ? { ...value } : {};
}


/**
 * No-operation function.
 *
 * Used as a safe fallback callback.
 *
 * @returns {void}
 */
export function noop() {
    // intentionally empty
}