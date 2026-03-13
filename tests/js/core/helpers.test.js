/**
 * AuthKit
 * -----------------------------------------------------------------------------
 * File: tests/js/core/helpers.test.js
 * Author: Michael Erastus
 * Package: AuthKit
 *
 * Description:
 * -----------------------------------------------------------------------------
 * Unit tests for shared AuthKit helper utilities.
 *
 * Responsibilities:
 * - Verify basic type guards.
 * - Verify array and boolean normalization helpers.
 * - Verify safe string normalization.
 * - Verify nested object path access.
 * - Verify safe cloning and noop behavior.
 */

import { describe, expect, it, vi } from 'vitest';

import {
    cloneObject,
    dataGet,
    hasOwn,
    isBoolean,
    isFunction,
    isObject,
    isString,
    noop,
    normalizeString,
    toArray,
    toBoolean,
} from '../../../public/authkit/js/core/helpers.js';


describe('core/helpers', () => {
    it('detects plain objects correctly', () => {
        expect(isObject({})).toBe(true);
        expect(isObject([])).toBe(false);
        expect(isObject(null)).toBe(false);
        expect(isObject('x')).toBe(false);
    });

    it('detects functions, strings, and booleans correctly', () => {
        expect(isFunction(() => {})).toBe(true);
        expect(isFunction('x')).toBe(false);

        expect(isString('hello')).toBe(true);
        expect(isString(123)).toBe(false);

        expect(isBoolean(true)).toBe(true);
        expect(isBoolean(false)).toBe(true);
        expect(isBoolean('true')).toBe(false);
    });

    it('checks own properties safely', () => {
        expect(hasOwn({ a: 1 }, 'a')).toBe(true);
        expect(hasOwn({ a: 1 }, 'b')).toBe(false);
        expect(hasOwn(null, 'a')).toBe(false);
    });

    it('normalizes values into arrays', () => {
        expect(toArray([1, 2])).toEqual([1, 2]);
        expect(toArray('x')).toEqual(['x']);
        expect(toArray(null)).toEqual([]);
        expect(toArray(undefined)).toEqual([]);
    });

    it('normalizes booleans defensively', () => {
        expect(toBoolean(true)).toBe(true);
        expect(toBoolean(false)).toBe(false);
        expect(toBoolean(1)).toBe(true);
        expect(toBoolean(0)).toBe(false);
        expect(toBoolean('true')).toBe(true);
        expect(toBoolean('yes')).toBe(true);
        expect(toBoolean('off')).toBe(false);
        expect(toBoolean('')).toBe(false);
    });

    it('normalizes strings safely', () => {
        expect(normalizeString('  hello  ')).toBe('hello');
        expect(normalizeString('   ', 'fallback')).toBe('fallback');
        expect(normalizeString(null, 'fallback')).toBe('fallback');
    });

    it('reads nested object paths safely', () => {
        const payload = {
            runtime: {
                nested: {
                    value: 42,
                },
            },
        };

        expect(dataGet(payload, 'runtime.nested.value')).toBe(42);
        expect(dataGet(payload, 'runtime.missing', 'fallback')).toBe('fallback');
        expect(dataGet(null, 'runtime.value', 'fallback')).toBe('fallback');
    });

    it('creates shallow object clones safely', () => {
        const original = { a: 1 };
        const copy = cloneObject(original);

        expect(copy).toEqual({ a: 1 });
        expect(copy).not.toBe(original);
        expect(cloneObject(null)).toEqual({});
    });

    it('provides a safe noop function', () => {
        expect(noop()).toBeUndefined();
    });
});