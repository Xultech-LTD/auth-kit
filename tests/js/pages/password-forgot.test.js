/**
 * AuthKit
 * -----------------------------------------------------------------------------
 * File: tests/js/pages/password-forgot.test.js
 * Author: Michael Erastus
 * Package: AuthKit
 *
 * Description:
 * -----------------------------------------------------------------------------
 * Unit tests for the AuthKit password-forgot page runtime module.
 *
 * Responsibilities:
 * - Verify password-forgot page boot eligibility.
 * - Verify forgot-password form and control discovery.
 * - Verify schema-safe request control resolution.
 * - Verify graceful handling of hidden context controls.
 * - Verify graceful handling of incomplete page markup.
 */

import { beforeEach, describe, expect, it } from 'vitest';

import {
    boot,
    getContextControls,
    getFormControls,
    getPasswordForgotForm,
    getPasswordForgotPageElements,
    getPrimaryRequestControl,
    getRequestControls,
    isCheckboxControl,
    isHiddenControl,
    isPasswordControl,
    isSubmitControl,
    isVisibleFormControl,
} from '../../../public/authkit/js/pages/password-forgot.js';


/**
 * Build a minimal AuthKit runtime context for password-forgot page-module
 * testing.
 *
 * @param {Object} [overrides={}]
 * @returns {Object}
 */
function createPasswordForgotTestContext(overrides = {}) {
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
                password_forgot: {
                    enabled: true,
                    pageKey: 'password_forgot',
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


describe('pages/password-forgot', () => {
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

        it('resolves all supported controls from the forgot-password form', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="password_forgot">
                    <form>
                        <input type="email" name="contact_email">
                        <button type="submit">Send reset link</button>
                    </form>
                </main>
            `;

            const form = document.querySelector('form');
            const controls = getFormControls(form);

            expect(controls).toHaveLength(2);
        });

        it('resolves the first form within the current forgot-password page', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="password_forgot">
                    <form id="first-form"></form>
                    <form id="second-form"></form>
                </main>
            `;

            const context = createPasswordForgotTestContext();
            const form = getPasswordForgotForm(context);

            expect(form).not.toBeNull();
            expect(form?.id).toBe('first-form');
        });

        it('returns null when the current forgot-password page has no form', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="password_forgot">
                    <div>No form present.</div>
                </main>
            `;

            const context = createPasswordForgotTestContext();

            expect(getPasswordForgotForm(context)).toBeNull();
        });
    });

    describe('request control resolution', () => {
        it('resolves visible request controls safely without assuming field names', () => {
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'context';

            const checkboxInput = document.createElement('input');
            checkboxInput.type = 'checkbox';
            checkboxInput.name = 'remember_device';

            const passwordInput = document.createElement('input');
            passwordInput.type = 'password';
            passwordInput.name = 'secret';

            const emailInput = document.createElement('input');
            emailInput.type = 'email';
            emailInput.name = 'contact_email';

            const alternateInput = document.createElement('input');
            alternateInput.type = 'text';
            alternateInput.name = 'username';

            const result = getRequestControls([
                hiddenInput,
                checkboxInput,
                passwordInput,
                emailInput,
                alternateInput,
            ]);

            expect(result).toEqual([emailInput, alternateInput]);
        });

        it('does not assume the request field is named email', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="password_forgot">
                    <form>
                        <input type="text" name="login_identifier">
                        <button type="submit">Send reset link</button>
                    </form>
                </main>
            `;

            const context = createPasswordForgotTestContext();
            const elements = getPasswordForgotPageElements(context);

            expect(elements.requestControls).toHaveLength(1);
            expect(elements.primaryRequestControl?.getAttribute('name')).toBe('login_identifier');
        });

        it('returns an empty array when no visible request control exists', () => {
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';

            const checkboxInput = document.createElement('input');
            checkboxInput.type = 'checkbox';

            const passwordInput = document.createElement('input');
            passwordInput.type = 'password';

            expect(
                getRequestControls([hiddenInput, checkboxInput, passwordInput])
            ).toEqual([]);
        });

        it('resolves the first visible request control as the primary one', () => {
            const firstInput = document.createElement('input');
            firstInput.type = 'email';
            firstInput.name = 'contact_email';

            const secondInput = document.createElement('input');
            secondInput.type = 'text';
            secondInput.name = 'username';

            expect(
                getPrimaryRequestControl([firstInput, secondInput])
            ).toBe(firstInput);
        });

        it('returns null when no primary request control can be resolved', () => {
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';

            expect(getPrimaryRequestControl([hiddenInput])).toBeNull();
        });

        it('resolves hidden context controls safely', () => {
            const hiddenA = document.createElement('input');
            hiddenA.type = 'hidden';
            hiddenA.name = 'context_a';

            const hiddenB = document.createElement('input');
            hiddenB.type = 'hidden';
            hiddenB.name = 'context_b';

            const visibleInput = document.createElement('input');
            visibleInput.type = 'email';
            visibleInput.name = 'contact_email';

            expect(getContextControls([hiddenA, visibleInput, hiddenB])).toEqual([
                hiddenA,
                hiddenB,
            ]);
        });
    });

    describe('page element discovery', () => {
        it('builds a normalized forgot-password page descriptor from rendered markup', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="password_forgot">
                    <div class="authkit-page-container">
                        <div class="authkit-card">
                            <form method="post" action="/forgot-password">
                                <input type="email" name="contact_email" autocomplete="email">
                                <button type="submit">Send reset link</button>
                            </form>

                            <div class="authkit-auth-footer">
                                <a href="/login">Back to login</a>
                            </div>
                        </div>
                    </div>
                </main>
            `;

            const context = createPasswordForgotTestContext();
            const elements = getPasswordForgotPageElements(context);

            expect(elements.page).not.toBeNull();
            expect(elements.form).not.toBeNull();

            expect(elements.controls).toHaveLength(2);
            expect(elements.visibleControls).toHaveLength(1);
            expect(elements.hiddenControls).toHaveLength(0);
            expect(elements.checkboxControls).toHaveLength(0);
            expect(elements.passwordControls).toHaveLength(0);
            expect(elements.submitControls).toHaveLength(1);
            expect(elements.links).toHaveLength(1);

            expect(elements.requestControls).toHaveLength(1);
            expect(elements.primaryRequestControl?.getAttribute('name')).toBe('contact_email');
            expect(elements.contextControls).toEqual([]);
        });

        it('handles a forgot-password page with hidden context safely', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="password_forgot">
                    <form>
                        <input type="hidden" name="tenant" value="acme">
                        <input type="text" name="identifier">
                        <button type="submit">Send reset code</button>
                    </form>
                </main>
            `;

            const context = createPasswordForgotTestContext();
            const elements = getPasswordForgotPageElements(context);

            expect(elements.hiddenControls).toHaveLength(1);
            expect(elements.contextControls).toHaveLength(1);
            expect(elements.primaryRequestControl?.getAttribute('name')).toBe('identifier');
        });

        it('handles a forgot-password page without links safely', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="password_forgot">
                    <form>
                        <input type="text" name="login_identifier">
                        <button type="submit">Send reset link</button>
                    </form>
                </main>
            `;

            const context = createPasswordForgotTestContext();
            const elements = getPasswordForgotPageElements(context);

            expect(elements.links).toEqual([]);
            expect(elements.submitControls).toHaveLength(1);
            expect(elements.primaryRequestControl?.getAttribute('name')).toBe('login_identifier');
        });

        it('handles a forgot-password page without a form safely', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="password_forgot">
                    <a href="/login">Back to login</a>
                </main>
            `;

            const context = createPasswordForgotTestContext();
            const elements = getPasswordForgotPageElements(context);

            expect(elements.form).toBeNull();
            expect(elements.controls).toEqual([]);
            expect(elements.visibleControls).toEqual([]);
            expect(elements.hiddenControls).toEqual([]);
            expect(elements.checkboxControls).toEqual([]);
            expect(elements.passwordControls).toEqual([]);
            expect(elements.submitControls).toEqual([]);
            expect(elements.requestControls).toEqual([]);
            expect(elements.primaryRequestControl).toBeNull();
            expect(elements.contextControls).toEqual([]);
            expect(elements.links).toHaveLength(1);
        });
    });

    describe('boot', () => {
        it('boots successfully on the forgot-password page', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="password_forgot">
                    <form>
                        <input type="email" name="contact_email">
                        <button type="submit">Send reset link</button>
                    </form>
                </main>
            `;

            const context = createPasswordForgotTestContext();
            const result = boot(context);

            expect(result).not.toBeNull();
            expect(result?.key).toBe('password_forgot');
            expect(result?.form).toBeInstanceOf(HTMLFormElement);
            expect(result?.primaryRequestControl?.getAttribute('name')).toBe('contact_email');
        });

        it('returns null when the current page is not the forgot-password page', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="login">
                    <form>
                        <input type="text" name="identity">
                        <button type="submit">Login</button>
                    </form>
                </main>
            `;

            const pageElement = document.querySelector('[data-authkit-page]');

            const context = createPasswordForgotTestContext({
                pageElement,
                page: {
                    key: 'login',
                    pageKey: 'login',
                    element: pageElement,
                    config: {},
                },
            });

            expect(boot(context)).toBeNull();
        });

        it('returns null when the supplied context is invalid', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="password_forgot"></main>
            `;

            expect(boot(null)).toBeNull();
            expect(boot(undefined)).toBeNull();
            expect(boot('invalid')).toBeNull();
        });
    });
});