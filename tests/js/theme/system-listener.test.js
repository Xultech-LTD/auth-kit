/**
 * AuthKit
 * -----------------------------------------------------------------------------
 * File: system-listener.test.js
 * Author: Michael Erastus
 * Package: AuthKit
 *
 * Description:
 * -----------------------------------------------------------------------------
 * Unit tests for system appearance listener utilities.
 *
 * Responsibilities:
 * - Verify media-query listener support detection.
 * - Verify low-level system color-scheme listener binding.
 * - Verify high-level system change callbacks only fire when preferred mode is
 *   currently "system".
 */

import { beforeEach, describe, expect, it, vi } from 'vitest';

import {
    bindSystemColorSchemeListener,
    bindSystemModeListener,
    getSystemColorSchemeMediaQuery,
    supportsLegacyMediaQueryListeners,
    supportsModernMediaQueryListeners,
} from '../../../public/authkit/js/modules/theme/system-listener.js';

import {
    installAuthKitConfig,
    installMatchMediaMock,
    resetThemeTestEnvironment,
} from './support/theme-test-helpers.js';


describe('theme/system-listener', () => {
    beforeEach(() => {
        resetThemeTestEnvironment();
        installAuthKitConfig();
    });

    it('resolves a system color-scheme media query object', () => {
        installMatchMediaMock(false);

        const mediaQueryList = getSystemColorSchemeMediaQuery();

        expect(mediaQueryList).not.toBeNull();
        expect(mediaQueryList.media).toBe('(prefers-color-scheme: dark)');
    });

    it('detects modern and legacy media-query listener support', () => {
        const media = installMatchMediaMock(false);

        expect(supportsModernMediaQueryListeners(media.mediaQueryList)).toBe(true);
        expect(supportsLegacyMediaQueryListeners(media.mediaQueryList)).toBe(true);
    });

    it('binds a low-level system color-scheme listener', () => {
        const media = installMatchMediaMock(false);
        const listener = vi.fn();

        bindSystemColorSchemeListener(listener);

        media.setDark(true);
        media.emitChange();

        expect(listener).toHaveBeenCalledTimes(1);
    });

    it('fires the high-level callback only when preferred mode is system', () => {
        const media = installMatchMediaMock(false);
        const onChange = vi.fn();

        bindSystemModeListener(() => 'system', onChange);

        media.setDark(true);
        media.emitChange();

        expect(onChange).toHaveBeenCalledTimes(1);
        expect(onChange.mock.calls[0][0].preferredMode).toBe('system');
        expect(onChange.mock.calls[0][0].resolvedMode).toBe('dark');
        expect(onChange.mock.calls[0][0].systemMode).toBe('dark');
    });

    it('does not fire the high-level callback when preferred mode is explicit', () => {
        const media = installMatchMediaMock(false);
        const onChange = vi.fn();

        bindSystemModeListener(() => 'dark', onChange);

        media.setDark(true);
        media.emitChange();

        expect(onChange).not.toHaveBeenCalled();
    });
});