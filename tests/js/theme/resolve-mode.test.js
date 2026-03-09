/**
 * AuthKit
 * -----------------------------------------------------------------------------
 * File: resolve-mode.test.js
 * Author: Michael Erastus
 * Package: AuthKit
 *
 * Description:
 * -----------------------------------------------------------------------------
 * Unit tests for theme mode resolution utilities.
 *
 * Responsibilities:
 * - Verify supported mode detection.
 * - Verify mode normalization behavior.
 * - Verify configured mode resolution.
 * - Verify system mode resolution.
 * - Verify preferred/resolved mode derivation.
 * - Verify full mode state resolution payloads.
 */

import { beforeEach, describe, expect, it } from 'vitest';

import {
    getConfiguredMode,
    getPreferredMode,
    getResolvedMode,
    getSystemMode,
    isSupportedMode,
    normalizeMode,
    resolveModeState,
    supportsSystemColorScheme,
} from '../../../public/authkit/js/modules/theme/resolve-mode.js';

import {
    installAuthKitConfig,
    installMatchMediaMock,
    resetThemeTestEnvironment,
} from './support/theme-test-helpers.js';


describe('theme/resolve-mode', () => {
    beforeEach(() => {
        resetThemeTestEnvironment();
        installAuthKitConfig();
        installMatchMediaMock(false);
    });

    it('recognizes supported appearance modes', () => {
        expect(isSupportedMode('light')).toBe(true);
        expect(isSupportedMode('dark')).toBe(true);
        expect(isSupportedMode('system')).toBe(true);

        expect(isSupportedMode('unknown')).toBe(false);
        expect(isSupportedMode(null)).toBe(false);
    });

    it('normalizes invalid modes to the provided fallback', () => {
        expect(normalizeMode('light')).toBe('light');
        expect(normalizeMode('dark')).toBe('dark');
        expect(normalizeMode('system')).toBe('system');

        expect(normalizeMode('invalid')).toBe('system');
        expect(normalizeMode('invalid', 'dark')).toBe('dark');
    });

    it('reads the configured default mode from runtime config', () => {
        installAuthKitConfig({
            ui: {
                mode: 'dark',
                persistence: {
                    enabled: true,
                    storageKey: 'authkit.ui.mode',
                },
                toggle: {
                    attribute: 'data-authkit-theme-toggle',
                    allowSystem: true,
                },
            },
        });

        expect(getConfiguredMode()).toBe('dark');
    });

    it('detects browser system color-scheme support', () => {
        expect(supportsSystemColorScheme()).toBe(true);
    });

    it('resolves the current system mode from matchMedia', () => {
        const media = installMatchMediaMock(true);

        expect(getSystemMode()).toBe('dark');

        media.setDark(false);

        expect(getSystemMode()).toBe('light');
    });

    it('prefers persisted mode over configured mode when valid', () => {
        installAuthKitConfig({
            ui: {
                mode: 'light',
                persistence: {
                    enabled: true,
                    storageKey: 'authkit.ui.mode',
                },
                toggle: {
                    attribute: 'data-authkit-theme-toggle',
                    allowSystem: true,
                },
            },
        });

        expect(getPreferredMode('dark')).toBe('dark');
        expect(getPreferredMode('system')).toBe('system');
        expect(getPreferredMode('invalid')).toBe('light');
        expect(getPreferredMode(null)).toBe('light');
    });

    it('resolves system preferred mode into light or dark', () => {
        const media = installMatchMediaMock(true);

        expect(getResolvedMode('system')).toBe('dark');

        media.setDark(false);

        expect(getResolvedMode('system')).toBe('light');
    });

    it('returns a complete normalized mode state payload', () => {
        const media = installMatchMediaMock(true);

        installAuthKitConfig({
            ui: {
                mode: 'system',
                persistence: {
                    enabled: true,
                    storageKey: 'authkit.ui.mode',
                },
                toggle: {
                    attribute: 'data-authkit-theme-toggle',
                    allowSystem: true,
                },
            },
        });

        const state = resolveModeState('system');

        expect(state).toEqual({
            configuredMode: 'system',
            preferredMode: 'system',
            resolvedMode: 'dark',
            systemMode: 'dark',
        });

        media.setDark(false);

        const nextState = resolveModeState('system');

        expect(nextState.resolvedMode).toBe('light');
        expect(nextState.systemMode).toBe('light');
    });
});