/**
 * AuthKit
 * -----------------------------------------------------------------------------
 * File: tests/js/pages/password-reset-success.test.js
 * Author: Michael Erastus
 * Package: AuthKit
 *
 * Description:
 * -----------------------------------------------------------------------------
 * Unit tests for the AuthKit password reset success page runtime module.
 *
 * Responsibilities:
 * - Verify password-reset-success page boot eligibility.
 * - Verify success-page link discovery.
 * - Verify graceful handling of pages without links.
 * - Verify graceful handling of unexpected forms.
 * - Verify normalized page descriptor output.
 */

import { beforeEach, describe, expect, it } from 'vitest';

import {
    boot,
    getPasswordResetSuccessPageElements,
    getPrimaryActionLink,
    getSuccessPageForms,
    getSuccessPageLinks,
} from '../../../public/authkit/js/pages/password-reset-success.js';


/**
 * Build a minimal AuthKit runtime context for password reset success
 * page-module testing.
 *
 * @param {Object} [overrides={}]
 * @returns {Object}
 */
function createPasswordResetSuccessTestContext(overrides = {}) {
    const pageElement = document.querySelector('[data-authkit-page]');

    return {
        root: document.documentElement,
        pageElement,
        page: {
            key: pageElement?.getAttribute('data-authkit-page') ?? null,
            pageKey: pageElement?.getAttribute('data-authkit-page') ?? null,
            element: pageElement,
            config: {},
        },
        config: {
            pages: {
                password_reset_success: {
                    enabled: true,
                    pageKey: 'password_reset_success',
                },
            },
        },
        moduleRegistry: {},
        pageRegistry: {},
        getRuntime() {
            return null;
        },
        getState() {
            return {};
        },
        emit() {
            return null;
        },
        ...overrides,
    };
}


describe('pages/password-reset-success', () => {
    beforeEach(() => {
        document.body.innerHTML = '';
    });

    describe('link discovery', () => {
        it('resolves all links rendered within the success page', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="password_reset_success">
                    <div class="authkit-card">
                        <a href="/login">Continue to login</a>
                        <a href="/support">Support</a>
                    </div>
                </main>
            `;

            const page = document.querySelector('[data-authkit-page]');
            const links = getSuccessPageLinks(page);

            expect(links).toHaveLength(2);
            expect(links[0]?.getAttribute('href')).toBe('/login');
            expect(links[1]?.getAttribute('href')).toBe('/support');
        });

        it('returns an empty array when the success page is invalid', () => {
            expect(getSuccessPageLinks(null)).toEqual([]);
            expect(getSuccessPageLinks({})).toEqual([]);
        });

        it('resolves the first rendered link as the primary action link', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="password_reset_success">
                    <a href="/login">Continue to login</a>
                    <a href="/dashboard">Dashboard</a>
                </main>
            `;

            const page = document.querySelector('[data-authkit-page]');
            const primaryLink = getPrimaryActionLink(page);

            expect(primaryLink).not.toBeNull();
            expect(primaryLink?.getAttribute('href')).toBe('/login');
        });

        it('returns null when no primary action link exists', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="password_reset_success">
                    <div>No link rendered.</div>
                </main>
            `;

            const page = document.querySelector('[data-authkit-page]');

            expect(getPrimaryActionLink(page)).toBeNull();
        });
    });

    describe('form discovery', () => {
        it('returns an empty forms collection when no form exists on the success page', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="password_reset_success">
                    <a href="/login">Continue to login</a>
                </main>
            `;

            const context = createPasswordResetSuccessTestContext();
            const forms = getSuccessPageForms(context);

            expect(forms).toEqual([]);
        });

        it('resolves forms when unexpected forms are rendered on the success page', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="password_reset_success">
                    <form id="unexpected-form"></form>
                    <a href="/login">Continue to login</a>
                </main>
            `;

            const context = createPasswordResetSuccessTestContext();
            const forms = getSuccessPageForms(context);

            expect(forms).toHaveLength(1);
            expect(forms[0]?.id).toBe('unexpected-form');
        });
    });

    describe('page element discovery', () => {
        it('builds a normalized password reset success page descriptor from rendered markup', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="password_reset_success">
                    <div class="authkit-page-container">
                        <div class="authkit-card">
                            <div class="ak-alert">Password reset successful.</div>
                            <a href="/login">Continue to login</a>
                        </div>
                    </div>
                </main>
            `;

            const context = createPasswordResetSuccessTestContext();
            const elements = getPasswordResetSuccessPageElements(context);

            expect(elements.page).not.toBeNull();
            expect(elements.forms).toEqual([]);
            expect(elements.formCount).toBe(0);
            expect(elements.links).toHaveLength(1);
            expect(elements.primaryActionLink).not.toBeNull();
            expect(elements.primaryActionLink?.getAttribute('href')).toBe('/login');
        });

        it('handles a success page without links safely', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="password_reset_success">
                    <div class="authkit-card">
                        <div class="ak-alert">Password reset successful.</div>
                    </div>
                </main>
            `;

            const context = createPasswordResetSuccessTestContext();
            const elements = getPasswordResetSuccessPageElements(context);

            expect(elements.links).toEqual([]);
            expect(elements.primaryActionLink).toBeNull();
            expect(elements.formCount).toBe(0);
        });

        it('handles a success page without a valid page element safely', () => {
            const context = createPasswordResetSuccessTestContext({
                pageElement: null,
                page: {
                    key: null,
                    pageKey: null,
                    element: null,
                    config: {},
                },
            });

            const elements = getPasswordResetSuccessPageElements(context);

            expect(elements.page).toBeNull();
            expect(elements.forms).toEqual([]);
            expect(elements.formCount).toBe(0);
            expect(elements.links).toEqual([]);
            expect(elements.primaryActionLink).toBeNull();
        });
    });

    describe('boot', () => {
        it('boots successfully on the password reset success page', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="password_reset_success">
                    <a href="/login">Continue to login</a>
                </main>
            `;

            const context = createPasswordResetSuccessTestContext();
            const result = boot(context);

            expect(result).not.toBeNull();
            expect(result?.key).toBe('password_reset_success');
            expect(result?.formCount).toBe(0);
            expect(result?.links).toHaveLength(1);
            expect(result?.primaryActionLink?.getAttribute('href')).toBe('/login');
        });

        it('returns null when the current page is not the password reset success page', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="email_verification_success">
                    <a href="/login">Continue to login</a>
                </main>
            `;

            const pageElement = document.querySelector('[data-authkit-page]');

            const context = createPasswordResetSuccessTestContext({
                pageElement,
                page: {
                    key: 'email_verification_success',
                    pageKey: 'email_verification_success',
                    element: pageElement,
                    config: {},
                },
            });

            expect(boot(context)).toBeNull();
        });

        it('returns null when the supplied context is invalid', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="password_reset_success"></main>
            `;

            expect(boot(null)).toBeNull();
            expect(boot(undefined)).toBeNull();
            expect(boot('invalid')).toBeNull();
        });
    });
});