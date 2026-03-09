/**
 * AuthKit
 * -----------------------------------------------------------------------------
 * File: persistence.test.js
 * Author: Michael Erastus
 * Package: AuthKit
 *
 * Description:
 * -----------------------------------------------------------------------------
 * Unit tests for theme mode persistence utilities.
 *
 * Responsibilities:
 * - Verify persistence enablement resolution.
 * - Verify storage key resolution.
 * - Verify reading and writing persisted theme modes.
 * - Verify invalid persisted values are ignored safely.
 * - Verify persisted mode removal behavior.
 */

import { beforeEach, describe, expect, it } from 'vitest';

import {
    clearPersistedMode,
    getPersistenceState,
    getStorageKey,
    isPersistenceEnabled,
    persistMode,
    readPersistedMode,
    readRawPersistedMode,
} from '../../../public/authkit/js/modules/theme/persistence.js';

import {
    installAuthKitConfig,
    resetThemeTestEnvironment,
} from './support/theme-test-helpers.js';


describe('theme/persistence', () => {
    beforeEach(() => {
        resetThemeTestEnvironment();
        installAuthKitConfig();
    });

    it('resolves persistence configuration correctly', () => {
        expect(isPersistenceEnabled()).toBe(true);
        expect(getStorageKey()).toBe('authkit.ui.mode');
    });

    it('persists and reads a valid preferred mode', () => {
        expect(persistMode('dark')).toBe(true);
        expect(readRawPersistedMode()).toBe('dark');
        expect(readPersistedMode()).toBe('dark');
    });

    it('rejects invalid theme modes for persistence', () => {
        expect(persistMode('invalid')).toBe(false);
        expect(readPersistedMode()).toBeNull();
    });

    it('ignores invalid raw values already present in storage', () => {
        window.localStorage.setItem('authkit.ui.mode', 'unknown');

        expect(readRawPersistedMode()).toBe('unknown');
        expect(readPersistedMode()).toBeNull();
    });

    it('clears persisted mode values', () => {
        persistMode('light');

        expect(readPersistedMode()).toBe('light');

        expect(clearPersistedMode()).toBe(true);
        expect(readPersistedMode()).toBeNull();
    });

    it('returns the effective persistence state payload', () => {
        persistMode('system');

        expect(getPersistenceState()).toEqual({
            enabled: true,
            storageKey: 'authkit.ui.mode',
            persistedMode: 'system',
        });
    });

    it('returns disabled behavior when persistence is turned off', () => {
        installAuthKitConfig({
            ui: {
                mode: 'system',
                persistence: {
                    enabled: false,
                    storageKey: 'authkit.ui.mode',
                },
                toggle: {
                    attribute: 'data-authkit-theme-toggle',
                    allowSystem: true,
                },
            },
        });

        expect(isPersistenceEnabled()).toBe(false);
        expect(readPersistedMode()).toBeNull();
        expect(persistMode('dark')).toBe(false);
        expect(clearPersistedMode()).toBe(false);
    });
});