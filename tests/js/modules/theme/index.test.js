/**
 * AuthKit
 * -----------------------------------------------------------------------------
 * File: tests/js/modules/theme/index.test.js
 * Author: Michael Erastus
 * Package: AuthKit
 *
 * Description:
 * -----------------------------------------------------------------------------
 * Integration-style tests for the AuthKit theme module entry point.
 *
 * Responsibilities:
 * - Verify initial theme boot behavior.
 * - Verify persisted preference restoration.
 * - Verify DOM application after boot.
 * - Verify toggle interaction through the booted theme module.
 * - Verify theme lifecycle events are emitted.
 *
 * Notes:
 * - These tests use dynamic imports so that module state is reset between runs.
 */

import { beforeEach, describe, expect, it, vi } from 'vitest';

import {
    installAuthKitConfig,
    installMatchMediaMock,
    resetThemeTestEnvironment,
} from './support/theme-test-helpers.js';


describe('theme/index', () => {
    beforeEach(() => {
        resetThemeTestEnvironment();
        installAuthKitConfig();
        installMatchMediaMock(false);
        vi.resetModules();
    });

    it('boots and applies an initial theme state to the document root', async () => {
        const themeModule = await import('../../../../resources/js/authkit/modules/theme/index.js');

        const api = themeModule.boot({});

        expect(api).toHaveProperty('getPreferredMode');
        expect(api).toHaveProperty('getResolvedMode');
        expect(api).toHaveProperty('setPreferredMode');

        expect(
            document.documentElement.getAttribute('data-authkit-mode-preference')
        ).toBe('system');

        expect(
            document.documentElement.getAttribute('data-authkit-mode')
        ).toBe('light');
    });

    it('restores a persisted mode during boot', async () => {
        window.localStorage.setItem('authkit.ui.mode', 'dark');

        const themeModule = await import('../../../../resources/js/authkit/modules/theme/index.js');

        themeModule.boot({});

        expect(
            document.documentElement.getAttribute('data-authkit-mode-preference')
        ).toBe('dark');

        expect(
            document.documentElement.getAttribute('data-authkit-mode')
        ).toBe('dark');
    });

    it('updates theme state when setPreferredMode is called', async () => {
        const themeModule = await import('../../../../resources/js/authkit/modules/theme/index.js');

        const api = themeModule.boot({});

        api.setPreferredMode('dark', { source: 'test' });

        expect(api.getPreferredMode()).toBe('dark');
        expect(api.getResolvedMode()).toBe('dark');

        expect(
            document.documentElement.getAttribute('data-authkit-mode-preference')
        ).toBe('dark');

        expect(
            window.localStorage.getItem('authkit.ui.mode')
        ).toBe('dark');
    });

    it('emits theme lifecycle events during boot and change', async () => {
        const onThemeReady = vi.fn();
        const onThemeChanged = vi.fn();

        document.addEventListener('authkit:theme:ready', onThemeReady);
        document.addEventListener('authkit:theme:changed', onThemeChanged);

        const themeModule = await import('../../../../resources/js/authkit/modules/theme/index.js');

        const api = themeModule.boot({});

        expect(onThemeReady).toHaveBeenCalledTimes(1);

        api.setPreferredMode('dark', { source: 'test' });

        expect(onThemeChanged).toHaveBeenCalledTimes(1);
    });

    it('binds packaged toggle controls during boot', async () => {
        document.body.innerHTML = `
            <button data-authkit-theme-toggle="dark">Dark</button>
            <button data-authkit-theme-toggle-cycle="1">Cycle</button>
        `;

        const themeModule = await import('../../../../resources/js/authkit/modules/theme/index.js');

        const api = themeModule.boot({});

        const darkButton = document.querySelector('[data-authkit-theme-toggle="dark"]');
        darkButton.click();

        expect(api.getPreferredMode()).toBe('dark');
        expect(api.getResolvedMode()).toBe('dark');
    });
});