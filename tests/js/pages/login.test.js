/**
 * AuthKit
 * -----------------------------------------------------------------------------
 * File: tests/js/pages/login.test.js
 * Author: Michael Erastus
 * Package: AuthKit
 *
 * Description:
 * -----------------------------------------------------------------------------
 * Unit tests for the AuthKit login page runtime module.
 *
 * Responsibilities:
 * - Verify login-page boot eligibility.
 * - Verify login form and control discovery.
 * - Verify schema-safe identity control resolution.
 * - Verify control classification helpers.
 * - Verify graceful handling of incomplete page markup.
 */

import { beforeEach, describe, expect, it } from 'vitest';

import {
    boot,
    getFormControls,
    getIdentityControl,
    getLoginForm,
    getLoginPageElements,
    isCheckboxControl,
    isHiddenControl,
    isPasswordControl,
    isSubmitControl,
    isVisibleFormControl,
} from '../../../resources/js/authkit/pages/login.js';


/**
 * Build a minimal AuthKit runtime context for page-module testing.
 *
 * The login page module only needs a valid-looking runtime context shape and a
 * page element reference. This helper keeps tests compact while remaining close
 * to the real runtime contract.
 *
 * @param {Object} [overrides={}]
 * @returns {Object}
 */
function createLoginTestContext(overrides = {}) {
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
                login: {
                    enabled: true,
                    pageKey: 'login',
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


describe('pages/login', () => {
    beforeEach(() => {
        document.body.innerHTML = '';
    });

    describe('control guards', () => {
        it('detects hidden controls correctly', () => {
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';

            const textInput = document.createElement('input');
            textInput.type = 'text';

            expect(isHiddenControl(hiddenInput)).toBe(true);
            expect(isHiddenControl(textInput)).toBe(false);
            expect(isHiddenControl(null)).toBe(false);
        });

        it('detects password controls correctly', () => {
            const passwordInput = document.createElement('input');
            passwordInput.type = 'password';

            const emailInput = document.createElement('input');
            emailInput.type = 'email';

            expect(isPasswordControl(passwordInput)).toBe(true);
            expect(isPasswordControl(emailInput)).toBe(false);
            expect(isPasswordControl(null)).toBe(false);
        });

        it('detects checkbox controls correctly', () => {
            const checkboxInput = document.createElement('input');
            checkboxInput.type = 'checkbox';

            const textInput = document.createElement('input');
            textInput.type = 'text';

            expect(isCheckboxControl(checkboxInput)).toBe(true);
            expect(isCheckboxControl(textInput)).toBe(false);
            expect(isCheckboxControl(null)).toBe(false);
        });

        it('detects submit controls correctly', () => {
            const submitButton = document.createElement('button');
            submitButton.type = 'submit';

            const implicitSubmitButton = document.createElement('button');

            const submitInput = document.createElement('input');
            submitInput.type = 'submit';

            const imageInput = document.createElement('input');
            imageInput.type = 'image';

            const textInput = document.createElement('input');
            textInput.type = 'text';

            expect(isSubmitControl(submitButton)).toBe(true);
            expect(isSubmitControl(implicitSubmitButton)).toBe(true);
            expect(isSubmitControl(submitInput)).toBe(true);
            expect(isSubmitControl(imageInput)).toBe(true);
            expect(isSubmitControl(textInput)).toBe(false);
            expect(isSubmitControl(null)).toBe(false);
        });

        it('detects visible form controls correctly', () => {
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';

            const textInput = document.createElement('input');
            textInput.type = 'text';

            const select = document.createElement('select');
            const textarea = document.createElement('textarea');
            const button = document.createElement('button');

            expect(isVisibleFormControl(hiddenInput)).toBe(false);
            expect(isVisibleFormControl(textInput)).toBe(true);
            expect(isVisibleFormControl(select)).toBe(true);
            expect(isVisibleFormControl(textarea)).toBe(true);
            expect(isVisibleFormControl(button)).toBe(false);
            expect(isVisibleFormControl(null)).toBe(false);
        });
    });

    describe('form discovery', () => {
        it('returns an empty array when resolving controls from an invalid form', () => {
            expect(getFormControls(null)).toEqual([]);
            expect(getFormControls({})).toEqual([]);
        });

        it('resolves all supported controls from the login form', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="login">
                    <form>
                        <input type="text" name="identity">
                        <input type="password" name="secret">
                        <input type="checkbox" name="remember">
                        <input type="hidden" name="_token" value="csrf-token">
                        <select name="role"></select>
                        <textarea name="note"></textarea>
                        <button type="submit">Continue</button>
                    </form>
                </main>
            `;

            const form = document.querySelector('form');
            const controls = getFormControls(form);

            expect(controls).toHaveLength(7);
        });

        it('resolves the first form within the current login page', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="login">
                    <form id="first-form"></form>
                    <form id="second-form"></form>
                </main>
            `;

            const context = createLoginTestContext();
            const form = getLoginForm(context);

            expect(form).not.toBeNull();
            expect(form?.id).toBe('first-form');
        });

        it('returns null when the current login page has no form', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="login">
                    <div>No form present.</div>
                </main>
            `;

            const context = createLoginTestContext();

            expect(getLoginForm(context)).toBeNull();
        });
    });

    describe('identity control resolution', () => {
        it('resolves the first visible non-password non-checkbox control', () => {
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = '_token';

            const rememberInput = document.createElement('input');
            rememberInput.type = 'checkbox';
            rememberInput.name = 'remember';

            const passwordInput = document.createElement('input');
            passwordInput.type = 'password';
            passwordInput.name = 'password';

            const usernameInput = document.createElement('input');
            usernameInput.type = 'text';
            usernameInput.name = 'username';

            const result = getIdentityControl([
                hiddenInput,
                rememberInput,
                passwordInput,
                usernameInput,
            ]);

            expect(result).toBe(usernameInput);
        });

        it('does not assume the identity field is named email', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="login">
                    <form>
                        <input type="text" name="username" autocomplete="username">
                        <input type="password" name="passcode">
                        <button type="submit">Login</button>
                    </form>
                </main>
            `;

            const context = createLoginTestContext();
            const elements = getLoginPageElements(context);

            expect(elements.identityControl).not.toBeNull();
            expect(elements.identityControl?.getAttribute('name')).toBe('username');
        });

        it('returns null when no visible identity-like control exists', () => {
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';

            const passwordInput = document.createElement('input');
            passwordInput.type = 'password';

            const checkboxInput = document.createElement('input');
            checkboxInput.type = 'checkbox';

            expect(
                getIdentityControl([hiddenInput, passwordInput, checkboxInput])
            ).toBeNull();
        });
    });

    describe('page element discovery', () => {
        it('builds a normalized login page descriptor from rendered markup', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="login">
                    <div class="authkit-page-container">
                        <div class="authkit-card">
                            <form method="post" action="/login">
                                <input type="hidden" name="_token" value="csrf-token">
                                <input type="text" name="username" autocomplete="username">
                                <input type="password" name="secret" autocomplete="current-password">
                                <input type="checkbox" name="remember_me" value="1" checked>

                                <div>
                                    <a href="/forgot-password">Forgot your password?</a>
                                </div>

                                <button type="submit">Continue</button>
                            </form>

                            <div class="authkit-auth-footer">
                                <a href="/register">Register</a>
                            </div>
                        </div>
                    </div>
                </main>
            `;

            const context = createLoginTestContext();
            const elements = getLoginPageElements(context);

            expect(elements.page).not.toBeNull();
            expect(elements.form).not.toBeNull();

            expect(elements.controls).toHaveLength(5);
            expect(elements.visibleControls).toHaveLength(3);
            expect(elements.hiddenControls).toHaveLength(1);
            expect(elements.passwordControls).toHaveLength(1);
            expect(elements.checkboxControls).toHaveLength(1);
            expect(elements.submitControls).toHaveLength(1);
            expect(elements.links).toHaveLength(2);

            expect(elements.identityControl).not.toBeNull();
            expect(elements.identityControl?.getAttribute('name')).toBe('username');
            expect(elements.passwordControls[0]?.getAttribute('name')).toBe('secret');
            expect(elements.checkboxControls[0]?.getAttribute('name')).toBe('remember_me');
        });

        it('handles a login page without auxiliary links safely', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="login">
                    <form>
                        <input type="text" name="identifier">
                        <input type="password" name="password">
                        <button type="submit">Continue</button>
                    </form>
                </main>
            `;

            const context = createLoginTestContext();
            const elements = getLoginPageElements(context);

            expect(elements.links).toEqual([]);
            expect(elements.submitControls).toHaveLength(1);
            expect(elements.identityControl?.getAttribute('name')).toBe('identifier');
        });

        it('handles a login page without a form safely', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="login">
                    <a href="/register">Register</a>
                </main>
            `;

            const context = createLoginTestContext();
            const elements = getLoginPageElements(context);

            expect(elements.form).toBeNull();
            expect(elements.controls).toEqual([]);
            expect(elements.visibleControls).toEqual([]);
            expect(elements.hiddenControls).toEqual([]);
            expect(elements.passwordControls).toEqual([]);
            expect(elements.checkboxControls).toEqual([]);
            expect(elements.submitControls).toEqual([]);
            expect(elements.links).toHaveLength(1);
            expect(elements.identityControl).toBeNull();
        });
    });

    describe('boot', () => {
        it('boots successfully on the login page', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="login">
                    <form>
                        <input type="text" name="login_id">
                        <input type="password" name="password">
                        <button type="submit">Continue</button>
                    </form>
                </main>
            `;

            const context = createLoginTestContext();
            const result = boot(context);

            expect(result).not.toBeNull();
            expect(result?.key).toBe('login');
            expect(result?.form).toBeInstanceOf(HTMLFormElement);
            expect(result?.identityControl?.getAttribute('name')).toBe('login_id');
        });

        it('returns null when the current page is not the login page', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="register">
                    <form>
                        <input type="text" name="name">
                        <button type="submit">Register</button>
                    </form>
                </main>
            `;

            const pageElement = document.querySelector('[data-authkit-page]');

            const context = createLoginTestContext({
                pageElement,
                page: {
                    key: 'register',
                    pageKey: 'register',
                    element: pageElement,
                    config: {},
                },
            });

            expect(boot(context)).toBeNull();
        });

        it('returns null when the supplied context is invalid', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="login"></main>
            `;

            expect(boot(null)).toBeNull();
            expect(boot(undefined)).toBeNull();
            expect(boot('invalid')).toBeNull();
        });
    });
});