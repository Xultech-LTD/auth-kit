/**
 * AuthKit
 * -----------------------------------------------------------------------------
 * File: tests/js/pages/password-reset.test.js
 * Author: Michael Erastus
 * Package: AuthKit
 *
 * Description:
 * -----------------------------------------------------------------------------
 * Unit tests for the AuthKit password-reset page runtime module.
 *
 * Responsibilities:
 * - Verify password-reset page boot eligibility.
 * - Verify reset form and control discovery.
 * - Verify schema-safe password and context control resolution.
 * - Verify password confirmation discovery by control type/order.
 * - Verify graceful handling of incomplete page markup.
 */

import { beforeEach, describe, expect, it } from 'vitest';

import {
    boot,
    getContextControls,
    getFormControls,
    getPasswordConfirmationControl,
    getPasswordControls,
    getPasswordResetForm,
    getPasswordResetPageElements,
    getPrimaryPasswordControl,
    getVisibleResetControls,
    isCheckboxControl,
    isHiddenControl,
    isPasswordControl,
    isSubmitControl,
    isVisibleFormControl,
} from '../../../resources/js/authkit/pages/password-reset.js';


/**
 * Build a minimal AuthKit runtime context for password-reset page-module testing.
 *
 * @param {Object} [overrides={}]
 * @returns {Object}
 */
function createPasswordResetTestContext(overrides = {}) {
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
                password_reset: {
                    enabled: true,
                    pageKey: 'password_reset',
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


describe('pages/password-reset', () => {
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

        it('resolves all supported controls from the reset form', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="password_reset">
                    <form>
                        <input type="hidden" name="contact_email" value="user@example.com">
                        <input type="hidden" name="reset_reference" value="token">
                        <input type="password" name="new_secret">
                        <input type="password" name="new_secret_repeat">
                        <button type="submit">Reset password</button>
                    </form>
                </main>
            `;

            const form = document.querySelector('form');
            const controls = getFormControls(form);

            expect(controls).toHaveLength(5);
        });

        it('resolves the first form within the current password-reset page', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="password_reset">
                    <form id="first-form"></form>
                    <form id="second-form"></form>
                </main>
            `;

            const context = createPasswordResetTestContext();
            const form = getPasswordResetForm(context);

            expect(form).not.toBeNull();
            expect(form?.id).toBe('first-form');
        });

        it('returns null when the current password-reset page has no form', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="password_reset">
                    <div>No form present.</div>
                </main>
            `;

            const context = createPasswordResetTestContext();

            expect(getPasswordResetForm(context)).toBeNull();
        });
    });

    describe('password and context control resolution', () => {
        it('resolves password controls by input type without assuming field names', () => {
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';

            const passwordInput = document.createElement('input');
            passwordInput.type = 'password';
            passwordInput.name = 'new_secret';

            const passwordConfirmationInput = document.createElement('input');
            passwordConfirmationInput.type = 'password';
            passwordConfirmationInput.name = 'new_secret_repeat';

            const passwordControls = getPasswordControls([
                hiddenInput,
                passwordInput,
                passwordConfirmationInput,
            ]);

            expect(passwordControls).toEqual([
                passwordInput,
                passwordConfirmationInput,
            ]);
        });

        it('resolves the first password control as the primary password field', () => {
            const passwordInput = document.createElement('input');
            passwordInput.type = 'password';
            passwordInput.name = 'new_secret';

            const passwordConfirmationInput = document.createElement('input');
            passwordConfirmationInput.type = 'password';
            passwordConfirmationInput.name = 'new_secret_repeat';

            expect(
                getPrimaryPasswordControl([passwordInput, passwordConfirmationInput])
            ).toBe(passwordInput);
        });

        it('resolves the second password control as the password confirmation field', () => {
            const passwordInput = document.createElement('input');
            passwordInput.type = 'password';
            passwordInput.name = 'new_secret';

            const passwordConfirmationInput = document.createElement('input');
            passwordConfirmationInput.type = 'password';
            passwordConfirmationInput.name = 'new_secret_repeat';

            expect(
                getPasswordConfirmationControl([passwordInput, passwordConfirmationInput])
            ).toBe(passwordConfirmationInput);
        });

        it('returns null when no primary password control can be resolved', () => {
            const textInput = document.createElement('input');
            textInput.type = 'text';

            expect(getPrimaryPasswordControl([textInput])).toBeNull();
        });

        it('returns null when no password confirmation control can be resolved', () => {
            const passwordInput = document.createElement('input');
            passwordInput.type = 'password';

            expect(getPasswordConfirmationControl([passwordInput])).toBeNull();
        });

        it('resolves hidden context controls safely', () => {
            const hiddenA = document.createElement('input');
            hiddenA.type = 'hidden';
            hiddenA.name = 'context_a';

            const hiddenB = document.createElement('input');
            hiddenB.type = 'hidden';
            hiddenB.name = 'context_b';

            const visibleInput = document.createElement('input');
            visibleInput.type = 'password';
            visibleInput.name = 'new_secret';

            expect(getContextControls([hiddenA, visibleInput, hiddenB])).toEqual([
                hiddenA,
                hiddenB,
            ]);
        });

        it('returns an empty visible-reset-controls collection when only password and hidden controls are rendered', () => {
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';

            const passwordInput = document.createElement('input');
            passwordInput.type = 'password';

            const passwordConfirmationInput = document.createElement('input');
            passwordConfirmationInput.type = 'password';

            expect(
                getVisibleResetControls([hiddenInput, passwordInput, passwordConfirmationInput])
            ).toEqual([]);
        });
    });

    describe('page element discovery', () => {
        it('builds a normalized password-reset page descriptor from rendered markup', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="password_reset">
                    <div class="authkit-page-container">
                        <div class="authkit-card">
                            <form method="post" action="/reset-password">
                                <input type="hidden" name="contact_email" value="user@example.com">
                                <input type="hidden" name="reset_reference" value="abc123">
                                <input type="password" name="new_secret" autocomplete="new-password">
                                <input type="password" name="new_secret_repeat" autocomplete="new-password">
                                <button type="submit">Reset password</button>
                            </form>

                            <div class="authkit-auth-footer">
                                <a href="/login">Back to login</a>
                            </div>
                        </div>
                    </div>
                </main>
            `;

            const context = createPasswordResetTestContext();
            const elements = getPasswordResetPageElements(context);

            expect(elements.page).not.toBeNull();
            expect(elements.form).not.toBeNull();

            expect(elements.controls).toHaveLength(5);
            expect(elements.visibleControls).toHaveLength(2);
            expect(elements.hiddenControls).toHaveLength(2);
            expect(elements.checkboxControls).toHaveLength(0);
            expect(elements.passwordControls).toHaveLength(2);
            expect(elements.submitControls).toHaveLength(1);
            expect(elements.links).toHaveLength(1);

            expect(elements.visibleResetControls).toEqual([]);
            expect(elements.primaryPasswordControl?.getAttribute('name')).toBe('new_secret');
            expect(elements.passwordConfirmationControl?.getAttribute('name')).toBe('new_secret_repeat');
            expect(elements.contextControls).toHaveLength(2);
        });

        it('handles a reset page with additional visible non-password controls safely', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="password_reset">
                    <form>
                        <input type="hidden" name="reset_reference" value="abc123">
                        <input type="text" name="security_hint">
                        <input type="password" name="new_secret">
                        <input type="password" name="new_secret_repeat">
                        <button type="submit">Reset password</button>
                    </form>
                </main>
            `;

            const context = createPasswordResetTestContext();
            const elements = getPasswordResetPageElements(context);

            expect(elements.visibleResetControls).toHaveLength(1);
            expect(elements.visibleResetControls[0]?.getAttribute('name')).toBe('security_hint');
            expect(elements.primaryPasswordControl?.getAttribute('name')).toBe('new_secret');
        });

        it('handles a reset page without links safely', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="password_reset">
                    <form>
                        <input type="password" name="new_secret">
                        <input type="password" name="new_secret_repeat">
                        <button type="submit">Reset password</button>
                    </form>
                </main>
            `;

            const context = createPasswordResetTestContext();
            const elements = getPasswordResetPageElements(context);

            expect(elements.links).toEqual([]);
            expect(elements.submitControls).toHaveLength(1);
            expect(elements.primaryPasswordControl?.getAttribute('name')).toBe('new_secret');
        });

        it('handles a reset page without a form safely', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="password_reset">
                    <a href="/login">Back to login</a>
                </main>
            `;

            const context = createPasswordResetTestContext();
            const elements = getPasswordResetPageElements(context);

            expect(elements.form).toBeNull();
            expect(elements.controls).toEqual([]);
            expect(elements.visibleControls).toEqual([]);
            expect(elements.hiddenControls).toEqual([]);
            expect(elements.checkboxControls).toEqual([]);
            expect(elements.passwordControls).toEqual([]);
            expect(elements.submitControls).toEqual([]);
            expect(elements.visibleResetControls).toEqual([]);
            expect(elements.primaryPasswordControl).toBeNull();
            expect(elements.passwordConfirmationControl).toBeNull();
            expect(elements.contextControls).toEqual([]);
            expect(elements.links).toHaveLength(1);
        });
    });

    describe('boot', () => {
        it('boots successfully on the password-reset page', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="password_reset">
                    <form>
                        <input type="hidden" name="reset_reference" value="abc123">
                        <input type="password" name="new_secret">
                        <input type="password" name="new_secret_repeat">
                        <button type="submit">Reset password</button>
                    </form>
                </main>
            `;

            const context = createPasswordResetTestContext();
            const result = boot(context);

            expect(result).not.toBeNull();
            expect(result?.key).toBe('password_reset');
            expect(result?.form).toBeInstanceOf(HTMLFormElement);
            expect(result?.primaryPasswordControl?.getAttribute('name')).toBe('new_secret');
            expect(result?.passwordConfirmationControl?.getAttribute('name')).toBe('new_secret_repeat');
            expect(result?.contextControls[0]?.getAttribute('name')).toBe('reset_reference');
        });

        it('returns null when the current page is not the password-reset page', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="password_reset_token">
                    <form>
                        <input type="text" name="verification_code">
                        <button type="submit">Continue</button>
                    </form>
                </main>
            `;

            const pageElement = document.querySelector('[data-authkit-page]');

            const context = createPasswordResetTestContext({
                pageElement,
                page: {
                    key: 'password_reset_token',
                    pageKey: 'password_reset_token',
                    element: pageElement,
                    config: {},
                },
            });

            expect(boot(context)).toBeNull();
        });

        it('returns null when the supplied context is invalid', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="password_reset"></main>
            `;

            expect(boot(null)).toBeNull();
            expect(boot(undefined)).toBeNull();
            expect(boot('invalid')).toBeNull();
        });
    });
});