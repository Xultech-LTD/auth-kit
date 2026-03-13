/**
 * AuthKit
 * -----------------------------------------------------------------------------
 * File: tests/js/core/runtime.test.js
 * Author: Michael Erastus
 * Package: AuthKit
 *
 * Description:
 * -----------------------------------------------------------------------------
 * Unit and integration-style tests for AuthKit runtime orchestration helpers.
 *
 * Responsibilities:
 * - Verify runtime context construction.
 * - Verify runtime state inspection helpers.
 * - Verify global namespace preservation.
 * - Verify single module and page boot behavior.
 * - Verify full runtime boot behavior and public api exposure.
 *
 * Notes:
 * - This file uses dynamic imports so module-local runtime state can be reset
 *   between tests.
 */

import { beforeEach, describe, expect, it, vi } from 'vitest';

import {
    installAuthKitConfig,
    resetCoreTestEnvironment,
} from './support/core-test-helpers.js';


describe('core/runtime', () => {
    beforeEach(() => {
        resetCoreTestEnvironment();
        installAuthKitConfig({
            pages: {
                login: {
                    enabled: true,
                    pageKey: 'login',
                },
            },
        });

        document.body.innerHTML = `
            <main class="authkit-page" data-authkit-page="login"></main>
        `;

        vi.resetModules();
    });

    it('creates a normalized runtime context', async () => {
        const runtime = await import('../../../resources/js/authkit/core/runtime.js');

        const context = runtime.createRuntimeContext({
            config: window.AuthKit.config,
            moduleRegistry: {},
            pageRegistry: {},
        });

        expect(context.root).toBe(document.documentElement);
        expect(context.pageElement).not.toBeNull();
        expect(context.page.pageKey).toBe('login');
    });

    it('ensures the AuthKit global namespace exists without replacing config', async () => {
        const runtime = await import('../../../resources/js/authkit/core/runtime.js');

        const namespace = runtime.ensureGlobalNamespace();

        expect(namespace).toBe(window.AuthKit);
        expect(namespace.config).toEqual(window.AuthKit.config);
    });

    it('boots individual shared modules safely', async () => {
        const runtime = await import('../../../resources/js/authkit/core/runtime.js');

        const moduleRegistry = {
            theme: {
                boot: vi.fn(() => ({ ok: true })),
            },
        };

        const context = runtime.createRuntimeContext({
            config: {
                modules: {
                    theme: {
                        enabled: true,
                    },
                },
            },
            moduleRegistry,
            pageRegistry: {},
        });

        const result = runtime.bootModule('theme', context, moduleRegistry);

        expect(result.booted).toBe(true);
        expect(result.result).toEqual({ ok: true });
    });

    it('boots configured modules through bootModules', async () => {
        const runtime = await import('../../../resources/js/authkit/core/runtime.js');

        const moduleRegistry = {
            theme: {
                boot: vi.fn(() => ({ theme: true })),
            },
            forms: {
                boot: vi.fn(() => ({ forms: true })),
            },
        };

        const context = runtime.createRuntimeContext({
            config: {
                modules: {
                    theme: { enabled: true },
                    forms: { enabled: false },
                },
            },
            moduleRegistry,
            pageRegistry: {},
        });

        const modules = runtime.bootModules(context, moduleRegistry);

        expect(modules.theme.booted).toBe(true);
        expect(modules.forms).toBeUndefined();
    });

    it('boots the active page module safely', async () => {
        const runtime = await import('../../../resources/js/authkit/core/runtime.js');

        const pageRegistry = {
            login: {
                boot: vi.fn(() => ({ page: true })),
            },
        };

        const context = runtime.createRuntimeContext({
            config: window.AuthKit.config,
            moduleRegistry: {},
            pageRegistry,
        });

        const page = runtime.bootPage(context, pageRegistry);

        expect(page.booted).toBe(true);
        expect(page.key).toBe('login');
        expect(page.result).toEqual({ page: true });
    });

    it('builds and exposes the public runtime api under window.AuthKit.runtime', async () => {
        const runtime = await import('../../../resources/js/authkit/core/runtime.js');

        const api = runtime.exposeRuntimeApi();

        expect(api).not.toBeNull();
        expect(window.AuthKit.runtime).toBe(api);
        expect(typeof window.AuthKit.runtime.isBooted).toBe('function');
    });

    it('boots the full runtime once and preserves global config', async () => {
        const runtime = await import('../../../resources/js/authkit/core/runtime.js');

        const moduleRegistry = {
            theme: {
                boot: vi.fn(() => ({ theme: true })),
            },
        };

        const pageRegistry = {
            login: {
                boot: vi.fn(() => ({ login: true })),
            },
        };

        const result = runtime.bootRuntime({
            config: {
                ...window.AuthKit.config,
                modules: {
                    theme: { enabled: true },
                },
            },
            moduleRegistry,
            pageRegistry,
        });

        expect(result.booted).toBe(true);
        expect(result.page.key).toBe('login');
        expect(result.modules.theme.booted).toBe(true);

        expect(window.AuthKit.config).toBeDefined();
        expect(window.AuthKit.runtime).toBeDefined();
        expect(window.AuthKit.runtime.isBooted()).toBe(true);
    });

    it('returns existing state when runtime is booted again', async () => {
        const runtime = await import('../../../resources/js/authkit/core/runtime.js');

        const first = runtime.bootRuntime({
            config: {
                ...window.AuthKit.config,
                modules: {},
            },
            moduleRegistry: {},
            pageRegistry: {},
        });

        const second = runtime.bootRuntime({
            config: {
                ...window.AuthKit.config,
                modules: {},
            },
            moduleRegistry: {},
            pageRegistry: {},
        });

        expect(first.booted).toBe(true);
        expect(second.booted).toBe(true);
    });
});