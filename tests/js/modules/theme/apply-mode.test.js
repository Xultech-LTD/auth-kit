/**
 * AuthKit
 * -----------------------------------------------------------------------------
 * File: tests/js/modules/theme/apply-mode.test.js
 * Author: Michael Erastus
 * Package: AuthKit
 *
 * Description:
 * -----------------------------------------------------------------------------
 * Unit tests for theme mode DOM application utilities.
 *
 * Responsibilities:
 * - Verify preferred mode application to document root attributes.
 * - Verify resolved mode application to document root attributes.
 * - Verify full mode state application behavior.
 * - Verify reading of applied theme attributes.
 */

import { beforeEach, describe, expect, it } from 'vitest';

import {
    applyModeState,
    applyPreferredMode,
    applyResolvedMode,
    readAppliedPreferredMode,
    readAppliedResolvedMode,
} from '../../../../resources/js/authkit/modules/theme/apply-mode.js';

import {
    resetThemeTestEnvironment,
} from './support/theme-test-helpers.js';


describe('theme/apply-mode', () => {
    beforeEach(() => {
        resetThemeTestEnvironment();
    });

    it('applies the preferred mode to the document root', () => {
        const applied = applyPreferredMode('system');

        expect(applied).toBe('system');
        expect(
            document.documentElement.getAttribute('data-authkit-mode-preference')
        ).toBe('system');
    });

    it('applies the resolved mode to the document root', () => {
        const applied = applyResolvedMode('dark');

        expect(applied).toBe('dark');
        expect(
            document.documentElement.getAttribute('data-authkit-mode-resolved')
        ).toBe('dark');
        expect(
            document.documentElement.getAttribute('data-authkit-mode')
        ).toBe('dark');
    });

    it('normalizes invalid resolved values safely when applying', () => {
        const applied = applyResolvedMode('system');

        expect(applied).toBe('light');
        expect(
            document.documentElement.getAttribute('data-authkit-mode')
        ).toBe('light');
    });

    it('applies a full mode state payload to the document root', () => {
        const result = applyModeState({
            configuredMode: 'system',
            preferredMode: 'dark',
            resolvedMode: 'dark',
            systemMode: 'light',
        });

        expect(result).toEqual({
            configuredMode: 'system',
            preferredMode: 'dark',
            resolvedMode: 'dark',
            systemMode: 'light',
        });

        expect(readAppliedPreferredMode()).toBe('dark');
        expect(readAppliedResolvedMode()).toBe('dark');
    });

    it('returns null when applyModeState receives invalid input', () => {
        expect(applyModeState(null)).toBeNull();
        expect(applyModeState('invalid')).toBeNull();
    });
});