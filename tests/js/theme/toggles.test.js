/**
 * AuthKit
 * -----------------------------------------------------------------------------
 * File: toggles.test.js
 * Author: Michael Erastus
 * Package: AuthKit
 *
 * Description:
 * -----------------------------------------------------------------------------
 * Unit tests for theme toggle discovery, synchronization, and interaction.
 *
 * Responsibilities:
 * - Verify discovery of packaged toggle controls.
 * - Verify explicit mode option reading.
 * - Verify cycle-mode behavior.
 * - Verify synchronization of button/select/cycle toggle UI state.
 * - Verify user interactions trigger the provided change callback.
 */

import { beforeEach, describe, expect, it, vi } from 'vitest';

import {
    bindThemeToggles,
    getAvailableModes,
    getNextToggleMode,
    getToggleControls,
    readToggleOptionMode,
    syncToggleState,
} from '../../../public/authkit/js/modules/theme/toggles.js';

import {
    installAuthKitConfig,
    resetThemeTestEnvironment,
} from './support/theme-test-helpers.js';


describe('theme/toggles', () => {
    beforeEach(() => {
        resetThemeTestEnvironment();
        installAuthKitConfig();

        document.body.innerHTML = `
            <button data-authkit-theme-toggle="light">Light</button>
            <button data-authkit-theme-toggle="dark">Dark</button>
            <button data-authkit-theme-toggle="system">System</button>

            <select data-authkit-theme-toggle-select="1">
                <option value="light">Light</option>
                <option value="dark">Dark</option>
                <option value="system">System</option>
            </select>

            <button data-authkit-theme-toggle-cycle="1">Cycle</button>
        `;
    });

    it('resolves available modes including system by default', () => {
        expect(getAvailableModes()).toEqual(['light', 'dark', 'system']);
    });

    it('discovers packaged toggle controls from the DOM', () => {
        const controls = getToggleControls();

        expect(controls.options).toHaveLength(3);
        expect(controls.selects).toHaveLength(1);
        expect(controls.cycleButtons).toHaveLength(1);
    });

    it('reads the target mode from explicit toggle option elements', () => {
        const button = document.querySelector('[data-authkit-theme-toggle="dark"]');

        expect(readToggleOptionMode(button)).toBe('dark');
    });

    it('computes the next cycle mode correctly', () => {
        expect(getNextToggleMode('light')).toBe('dark');
        expect(getNextToggleMode('dark')).toBe('system');
        expect(getNextToggleMode('system')).toBe('light');
    });

    it('synchronizes option, select, and cycle toggle state', () => {
        syncToggleState('dark');

        const darkButton = document.querySelector('[data-authkit-theme-toggle="dark"]');
        const lightButton = document.querySelector('[data-authkit-theme-toggle="light"]');
        const select = document.querySelector('[data-authkit-theme-toggle-select]');
        const cycle = document.querySelector('[data-authkit-theme-toggle-cycle]');

        expect(darkButton.getAttribute('aria-pressed')).toBe('true');
        expect(lightButton.getAttribute('aria-pressed')).toBe('false');

        expect(select.value).toBe('dark');

        expect(cycle.getAttribute('data-authkit-theme-toggle-current')).toBe('dark');
        expect(cycle.getAttribute('data-authkit-theme-toggle-next')).toBe('system');
    });

    it('binds explicit option button clicks to the provided change callback', () => {
        const onChange = vi.fn();

        bindThemeToggles(onChange, {
            getCurrentMode: () => 'light',
        });

        const darkButton = document.querySelector('[data-authkit-theme-toggle="dark"]');
        darkButton.click();

        expect(onChange).toHaveBeenCalledTimes(1);
        expect(onChange.mock.calls[0][0]).toBe('dark');
        expect(onChange.mock.calls[0][1].source).toBe('option');
    });

    it('binds select changes to the provided change callback', () => {
        const onChange = vi.fn();

        bindThemeToggles(onChange, {
            getCurrentMode: () => 'light',
        });

        const select = document.querySelector('[data-authkit-theme-toggle-select]');
        select.value = 'system';
        select.dispatchEvent(new Event('change', { bubbles: true }));

        expect(onChange).toHaveBeenCalledTimes(1);
        expect(onChange.mock.calls[0][0]).toBe('system');
        expect(onChange.mock.calls[0][1].source).toBe('select');
    });

    it('binds cycle button clicks using the current mode callback', () => {
        const onChange = vi.fn();

        bindThemeToggles(onChange, {
            getCurrentMode: () => 'dark',
        });

        const cycle = document.querySelector('[data-authkit-theme-toggle-cycle]');
        cycle.click();

        expect(onChange).toHaveBeenCalledTimes(1);
        expect(onChange.mock.calls[0][0]).toBe('system');
        expect(onChange.mock.calls[0][1].source).toBe('cycle');
    });

    it('excludes system mode when toggle config disables it', () => {
        installAuthKitConfig({
            ui: {
                mode: 'system',
                persistence: {
                    enabled: true,
                    storageKey: 'authkit.ui.mode',
                },
                toggle: {
                    attribute: 'data-authkit-theme-toggle',
                    allowSystem: false,
                },
            },
        });

        expect(getAvailableModes()).toEqual(['light', 'dark']);
        expect(getNextToggleMode('dark')).toBe('light');
    });
});