/**
 * AuthKit
 * -----------------------------------------------------------------------------
 * File: tests/js/core/config.test.js
 * Author: Michael Erastus
 * Package: AuthKit
 *
 * Description:
 * -----------------------------------------------------------------------------
 * Unit tests for AuthKit runtime configuration helpers.
 *
 * Responsibilities:
 * - Verify config cache behavior.
 * - Verify global window key resolution.
 * - Verify global and raw config access.
 * - Verify merged config defaults and overrides.
 * - Verify config lookup helpers and module/page enablement helpers.
 */

import { beforeEach, describe, expect, it } from 'vitest';

import {
    clearConfigCache,
    getConfig,
    getConfigValue,
    getDefaultConfig,
    getGlobalObject,
    getPageMarker,
    getRawConfig,
    getRuntimeEventTarget,
    getWindowKey,
    hasConfigValue,
    isModuleEnabled,
    isPageEnabled,
    setConfig,
    shouldDispatchEvents,
} from '../../../resources/js/authkit/core/config.js';

import {
    installAuthKitConfig,
    resetCoreTestEnvironment,
} from './support/core-test-helpers.js';


describe('core/config', () => {
    beforeEach(() => {
        resetCoreTestEnvironment();
        clearConfigCache();
    });

    it('falls back to the default window key when no config exists', () => {
        expect(getWindowKey()).toBe('AuthKit');
    });

    it('resolves the window key from the AuthKit global config', () => {
        installAuthKitConfig({
            runtime: {
                windowKey: 'AuthKit',
                dispatchEvents: true,
                eventTarget: 'document',
            },
        });

        expect(getWindowKey()).toBe('AuthKit');
    });

    it('resolves the window key from the generic preboot payload', () => {
        window.__AUTHKIT__ = {
            windowKey: 'AuthKit',
        };

        expect(getWindowKey()).toBe('AuthKit');
    });

    it('reads global and raw config payloads safely', () => {
        const config = installAuthKitConfig();

        expect(getGlobalObject()).toEqual({ config });
        expect(getRawConfig()).toEqual(config);
    });

    it('returns merged config with defaults and overrides', () => {
        installAuthKitConfig({
            ui: {
                mode: 'dark',
            },
        });

        const config = getConfig();

        expect(config.runtime.windowKey).toBe('AuthKit');
        expect(config.ui.mode).toBe('dark');
        expect(config.modules.theme.enabled).toBe(true);
    });

    it('reads nested config values and existence safely', () => {
        installAuthKitConfig();

        expect(getConfigValue('runtime.windowKey')).toBe('AuthKit');
        expect(getConfigValue('missing.path', 'fallback')).toBe('fallback');

        expect(hasConfigValue('runtime.windowKey')).toBe(true);
        expect(hasConfigValue('runtime.missing')).toBe(false);
    });

    it('resolves module and page enablement from config', () => {
        installAuthKitConfig({
            modules: {
                theme: { enabled: true },
                forms: { enabled: false },
            },
            pages: {
                login: {
                    enabled: true,
                    pageKey: 'login',
                },
                register: {
                    enabled: false,
                    pageKey: 'register',
                },
            },
        });

        expect(isModuleEnabled('theme')).toBe(true);
        expect(isModuleEnabled('forms')).toBe(false);

        expect(isPageEnabled('login')).toBe(true);
        expect(isPageEnabled('register')).toBe(false);
    });

    it('resolves configured page marker and runtime event settings', () => {
        installAuthKitConfig({
            runtime: {
                windowKey: 'AuthKit',
                dispatchEvents: false,
                eventTarget: 'window',
            },
            pages: {
                login: {
                    enabled: true,
                    page_key: 'login-page',
                },
            },
        });

        expect(getPageMarker('login')).toBe('login-page');
        expect(getRuntimeEventTarget()).toBe('window');
        expect(shouldDispatchEvents()).toBe(false);
    });

    it('returns a cloned default config payload', () => {
        const defaults = getDefaultConfig();

        expect(defaults.runtime.windowKey).toBe('AuthKit');
        expect(defaults.modules.theme.enabled).toBe(true);
    });

    it('sets and merges runtime config on the global object', () => {
        installAuthKitConfig();

        const config = setConfig({
            ui: {
                mode: 'dark',
            },
            pages: {
                password_reset: {
                    enabled: true,
                    pageKey: 'password_reset',
                },
            },
        });

        expect(config.ui.mode).toBe('dark');
        expect(config.pages.password_reset.enabled).toBe(true);
    });
});