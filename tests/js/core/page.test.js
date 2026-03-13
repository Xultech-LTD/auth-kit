/**
 * AuthKit
 * -----------------------------------------------------------------------------
 * File: tests/js/core/page.test.js
 * Author: Michael Erastus
 * Package: AuthKit
 *
 * Description:
 * -----------------------------------------------------------------------------
 * Unit tests for AuthKit page resolution helpers.
 *
 * Responsibilities:
 * - Verify config-backed page definition access.
 * - Verify current page key and element detection.
 * - Verify enabled/matching page checks.
 * - Verify active page resolution and page context payloads.
 */

import { beforeEach, describe, expect, it } from 'vitest';

import {
    getConfiguredPageKey,
    getCurrentPageElement,
    getCurrentPageKey,
    getPageConfig,
    getPageConfigs,
    getPageContext,
    hasActivePage,
    isCurrentPage,
    isPageEnabled,
    matchesCurrentPage,
    resolveActivePage,
} from '../../../public/authkit/js/core/page.js';

import {
    installAuthKitConfig,
    resetCoreTestEnvironment,
} from './support/core-test-helpers.js';


describe('core/page', () => {
    beforeEach(() => {
        resetCoreTestEnvironment();

        installAuthKitConfig({
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

        document.body.innerHTML = `
            <main class="authkit-page" data-authkit-page="login"></main>
        `;
    });

    it('resolves configured page maps and individual page config', () => {
        expect(getPageConfigs()).toHaveProperty('login');
        expect(getPageConfig('login')).toEqual({
            enabled: true,
            pageKey: 'login',
        });
        expect(getPageConfig('missing')).toBeNull();
    });

    it('resolves the current page key and element from the DOM', () => {
        expect(getCurrentPageKey()).toBe('login');
        expect(getCurrentPageElement()).not.toBeNull();
    });

    it('checks current page matching correctly', () => {
        expect(isCurrentPage('login')).toBe(true);
        expect(isCurrentPage('register')).toBe(false);
    });

    it('resolves configured page keys and enablement', () => {
        expect(getConfiguredPageKey('login')).toBe('login');
        expect(getConfiguredPageKey('password_reset')).toBe('password_reset');

        expect(isPageEnabled('login')).toBe(true);
        expect(isPageEnabled('register')).toBe(false);
    });

    it('checks whether a configured page matches the current page', () => {
        expect(matchesCurrentPage('login')).toBe(true);
        expect(matchesCurrentPage('register')).toBe(false);
    });

    it('resolves the active page entry from config and DOM', () => {
        expect(resolveActivePage()).toEqual({
            key: 'login',
            pageKey: 'login',
            config: {
                enabled: true,
                pageKey: 'login',
            },
        });

        expect(hasActivePage()).toBe(true);
    });

    it('builds a normalized page context payload', () => {
        expect(getPageContext()).toEqual({
            key: 'login',
            pageKey: 'login',
            element: getCurrentPageElement(),
            config: {
                enabled: true,
                pageKey: 'login',
            },
        });
    });
});