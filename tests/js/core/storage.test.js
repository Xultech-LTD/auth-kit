/**
 * AuthKit
 * -----------------------------------------------------------------------------
 * File: storage.test.js
 * Author: Michael Erastus
 * Package: AuthKit
 *
 * Description:
 * -----------------------------------------------------------------------------
 * Unit tests for AuthKit browser storage helpers.
 *
 * Responsibilities:
 * - Verify safe storage resolution.
 * - Verify storage availability checks.
 * - Verify raw and JSON read/write/remove helpers.
 * - Verify storage adapter behavior.
 */

import { beforeEach, describe, expect, it } from 'vitest';

import {
    createStorage,
    getItem,
    getJson,
    isStorageAvailable,
    removeItem,
    resolveStorage,
    setItem,
    setJson,
} from '../../../public/authkit/js/core/storage.js';

import { resetCoreTestEnvironment } from './support/core-test-helpers.js';


describe('core/storage', () => {
    beforeEach(() => {
        resetCoreTestEnvironment();
    });

    it('resolves local and session storage safely', () => {
        expect(resolveStorage('local')).toBe(window.localStorage);
        expect(resolveStorage('session')).toBe(window.sessionStorage);
    });

    it('detects storage availability', () => {
        expect(isStorageAvailable('local')).toBe(true);
        expect(isStorageAvailable('session')).toBe(true);
    });

    it('writes and reads raw storage values', () => {
        expect(setItem('token', 'abc')).toBe(true);
        expect(getItem('token')).toBe('abc');
    });

    it('removes stored values', () => {
        setItem('token', 'abc');

        expect(removeItem('token')).toBe(true);
        expect(getItem('token')).toBeNull();
    });

    it('rejects invalid raw storage keys and object values', () => {
        expect(setItem('', 'abc')).toBe(false);
        expect(setItem('payload', { a: 1 })).toBe(false);
    });

    it('writes and reads JSON values', () => {
        expect(setJson('user', { id: 1, name: 'Michael' })).toBe(true);

        expect(getJson('user')).toEqual({
            id: 1,
            name: 'Michael',
        });
    });

    it('returns fallback for invalid JSON content', () => {
        window.localStorage.setItem('broken', '{invalid');

        expect(getJson('broken', 'fallback')).toBe('fallback');
    });

    it('creates a storage adapter for compact usage', () => {
        const storage = createStorage('local');

        expect(storage.available()).toBe(true);

        expect(storage.set('mode', 'dark')).toBe(true);
        expect(storage.get('mode')).toBe('dark');

        expect(storage.setJson('user', { id: 2 })).toBe(true);
        expect(storage.getJson('user')).toEqual({ id: 2 });

        expect(storage.remove('mode')).toBe(true);
        expect(storage.get('mode')).toBeNull();
    });
});