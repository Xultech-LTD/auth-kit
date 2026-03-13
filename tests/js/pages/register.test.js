/**
 * AuthKit
 * -----------------------------------------------------------------------------
 * File: tests/js/pages/register.test.js
 * Author: Michael Erastus
 * Package: AuthKit
 *
 * Description:
 * -----------------------------------------------------------------------------
 * Unit tests for the AuthKit register page runtime module.
 *
 * Responsibilities:
 * - Verify register-page boot eligibility.
 * - Verify register form and control discovery.
 * - Verify schema-safe visible field resolution.
 * - Verify password and password-confirmation discovery by control type/order.
 * - Verify graceful handling of incomplete page markup.
 */

import { beforeEach, describe, expect, it } from 'vitest';

import {
    boot,
    getFormControls,
    getIdentityLikeControls,
    getPasswordConfirmationControl,
    getPasswordControls,
    getPrimaryIdentityControl,
    getPrimaryPasswordControl,
    getRegisterForm,
    getRegisterPageElements,
    isCheckboxControl,
    isHiddenControl,
    isPasswordControl,
    isSubmitControl,
    isVisibleFormControl,
} from '../../../resources/js/authkit/pages/register.js';


/**
 * Build a minimal AuthKit runtime context for register page-module testing.
 *
 * The register page module only needs a valid runtime-like context shape and a
 * page element reference. This helper keeps tests compact while remaining close
 * to the real runtime contract.
 *
 * @param {Object} [overrides={}]
 * @returns {Object}
 */
function createRegisterTestContext(overrides = {}) {
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
                register: {
                    enabled: true,
                    pageKey: 'register',
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


describe('pages/register', () => {
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

        it('resolves all supported controls from the register form', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="register">
                    <form>
                        <input type="text" name="full_name">
                        <input type="email" name="contact_email">
                        <input type="password" name="secret">
                        <input type="password" name="secret_confirmation">
                        <input type="checkbox" name="terms">
                        <input type="hidden" name="_token" value="csrf-token">
                        <select name="role"></select>
                        <textarea name="bio"></textarea>
                        <button type="submit">Create account</button>
                    </form>
                </main>
            `;

            const form = document.querySelector('form');
            const controls = getFormControls(form);

            expect(controls).toHaveLength(9);
        });

        it('resolves the first form within the current register page', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="register">
                    <form id="first-form"></form>
                    <form id="second-form"></form>
                </main>
            `;

            const context = createRegisterTestContext();
            const form = getRegisterForm(context);

            expect(form).not.toBeNull();
            expect(form?.id).toBe('first-form');
        });

        it('returns null when the current register page has no form', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="register">
                    <div>No form present.</div>
                </main>
            `;

            const context = createRegisterTestContext();

            expect(getRegisterForm(context)).toBeNull();
        });
    });

    describe('visible identity-like control resolution', () => {
        it('resolves visible non-password non-checkbox controls safely', () => {
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = '_token';

            const termsInput = document.createElement('input');
            termsInput.type = 'checkbox';
            termsInput.name = 'terms';

            const passwordInput = document.createElement('input');
            passwordInput.type = 'password';
            passwordInput.name = 'password';

            const fullNameInput = document.createElement('input');
            fullNameInput.type = 'text';
            fullNameInput.name = 'full_name';

            const emailInput = document.createElement('input');
            emailInput.type = 'email';
            emailInput.name = 'email_address';

            const result = getIdentityLikeControls([
                hiddenInput,
                termsInput,
                passwordInput,
                fullNameInput,
                emailInput,
            ]);

            expect(result).toEqual([fullNameInput, emailInput]);
        });

        it('does not assume the visible fields are named name or email', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="register">
                    <form>
                        <input type="text" name="display_name">
                        <input type="tel" name="mobile">
                        <input type="password" name="passcode">
                        <input type="password" name="passcode_repeat">
                        <button type="submit">Create account</button>
                    </form>
                </main>
            `;

            const context = createRegisterTestContext();
            const elements = getRegisterPageElements(context);

            expect(elements.identityLikeControls).toHaveLength(2);
            expect(elements.identityLikeControls[0]?.getAttribute('name')).toBe('display_name');
            expect(elements.identityLikeControls[1]?.getAttribute('name')).toBe('mobile');
            expect(elements.primaryIdentityControl?.getAttribute('name')).toBe('display_name');
        });

        it('returns an empty array when no visible identity-like control exists', () => {
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';

            const passwordInput = document.createElement('input');
            passwordInput.type = 'password';

            const checkboxInput = document.createElement('input');
            checkboxInput.type = 'checkbox';

            expect(
                getIdentityLikeControls([hiddenInput, passwordInput, checkboxInput])
            ).toEqual([]);
        });

        it('resolves the first visible identity-like control as the primary one', () => {
            const fullNameInput = document.createElement('input');
            fullNameInput.type = 'text';
            fullNameInput.name = 'full_name';

            const emailInput = document.createElement('input');
            emailInput.type = 'email';
            emailInput.name = 'email_address';

            expect(
                getPrimaryIdentityControl([fullNameInput, emailInput])
            ).toBe(fullNameInput);
        });

        it('returns null when no primary identity-like control can be resolved', () => {
            const passwordInput = document.createElement('input');
            passwordInput.type = 'password';

            expect(getPrimaryIdentityControl([passwordInput])).toBeNull();
        });
    });

    describe('password control resolution', () => {
        it('resolves password controls by input type without assuming field names', () => {
            const fullNameInput = document.createElement('input');
            fullNameInput.type = 'text';

            const passwordInput = document.createElement('input');
            passwordInput.type = 'password';
            passwordInput.name = 'secret';

            const passwordConfirmationInput = document.createElement('input');
            passwordConfirmationInput.type = 'password';
            passwordConfirmationInput.name = 'secret_repeat';

            const passwordControls = getPasswordControls([
                fullNameInput,
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
            passwordInput.name = 'password';

            const passwordConfirmationInput = document.createElement('input');
            passwordConfirmationInput.type = 'password';
            passwordConfirmationInput.name = 'password_confirmation';

            expect(
                getPrimaryPasswordControl([passwordInput, passwordConfirmationInput])
            ).toBe(passwordInput);
        });

        it('resolves the second password control as the password confirmation field', () => {
            const passwordInput = document.createElement('input');
            passwordInput.type = 'password';
            passwordInput.name = 'password';

            const passwordConfirmationInput = document.createElement('input');
            passwordConfirmationInput.type = 'password';
            passwordConfirmationInput.name = 'password_confirmation';

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
    });

    describe('page element discovery', () => {
        it('builds a normalized register page descriptor from rendered markup', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="register">
                    <div class="authkit-page-container">
                        <div class="authkit-card">
                            <form method="post" action="/register">
                                <input type="hidden" name="_token" value="csrf-token">
                                <input type="text" name="full_name" autocomplete="name">
                                <input type="email" name="account_email" autocomplete="email">
                                <input type="password" name="secret" autocomplete="new-password">
                                <input type="password" name="secret_repeat" autocomplete="new-password">

                                <button type="submit">Create account</button>
                            </form>

                            <div class="authkit-auth-footer">
                                <a href="/login">Login</a>
                            </div>
                        </div>
                    </div>
                </main>
            `;

            const context = createRegisterTestContext();
            const elements = getRegisterPageElements(context);

            expect(elements.page).not.toBeNull();
            expect(elements.form).not.toBeNull();

            expect(elements.controls).toHaveLength(6);
            expect(elements.visibleControls).toHaveLength(4);
            expect(elements.hiddenControls).toHaveLength(1);
            expect(elements.checkboxControls).toHaveLength(0);
            expect(elements.passwordControls).toHaveLength(2);
            expect(elements.submitControls).toHaveLength(1);
            expect(elements.links).toHaveLength(1);

            expect(elements.identityLikeControls).toHaveLength(2);
            expect(elements.primaryIdentityControl?.getAttribute('name')).toBe('full_name');
            expect(elements.primaryPasswordControl?.getAttribute('name')).toBe('secret');
            expect(elements.passwordConfirmationControl?.getAttribute('name')).toBe('secret_repeat');
        });

        it('handles a register page with checkbox-based consent fields safely', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="register">
                    <form>
                        <input type="text" name="nickname">
                        <input type="password" name="passcode">
                        <input type="password" name="passcode_repeat">
                        <input type="checkbox" name="terms">
                        <button type="submit">Create account</button>
                    </form>
                </main>
            `;

            const context = createRegisterTestContext();
            const elements = getRegisterPageElements(context);

            expect(elements.checkboxControls).toHaveLength(1);
            expect(elements.identityLikeControls).toHaveLength(1);
            expect(elements.primaryIdentityControl?.getAttribute('name')).toBe('nickname');
            expect(elements.passwordConfirmationControl?.getAttribute('name')).toBe('passcode_repeat');
        });

        it('handles a register page without links safely', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="register">
                    <form>
                        <input type="text" name="display_name">
                        <input type="password" name="secret">
                        <input type="password" name="secret_confirmation">
                        <button type="submit">Create account</button>
                    </form>
                </main>
            `;

            const context = createRegisterTestContext();
            const elements = getRegisterPageElements(context);

            expect(elements.links).toEqual([]);
            expect(elements.submitControls).toHaveLength(1);
            expect(elements.primaryIdentityControl?.getAttribute('name')).toBe('display_name');
        });

        it('handles a register page without a form safely', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="register">
                    <a href="/login">Login</a>
                </main>
            `;

            const context = createRegisterTestContext();
            const elements = getRegisterPageElements(context);

            expect(elements.form).toBeNull();
            expect(elements.controls).toEqual([]);
            expect(elements.visibleControls).toEqual([]);
            expect(elements.hiddenControls).toEqual([]);
            expect(elements.checkboxControls).toEqual([]);
            expect(elements.passwordControls).toEqual([]);
            expect(elements.submitControls).toEqual([]);
            expect(elements.identityLikeControls).toEqual([]);
            expect(elements.primaryIdentityControl).toBeNull();
            expect(elements.primaryPasswordControl).toBeNull();
            expect(elements.passwordConfirmationControl).toBeNull();
            expect(elements.links).toHaveLength(1);
        });
    });

    describe('boot', () => {
        it('boots successfully on the register page', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="register">
                    <form>
                        <input type="text" name="profile_name">
                        <input type="password" name="password">
                        <input type="password" name="password_repeat">
                        <button type="submit">Create account</button>
                    </form>
                </main>
            `;

            const context = createRegisterTestContext();
            const result = boot(context);

            expect(result).not.toBeNull();
            expect(result?.key).toBe('register');
            expect(result?.form).toBeInstanceOf(HTMLFormElement);
            expect(result?.primaryIdentityControl?.getAttribute('name')).toBe('profile_name');
            expect(result?.primaryPasswordControl?.getAttribute('name')).toBe('password');
            expect(result?.passwordConfirmationControl?.getAttribute('name')).toBe('password_repeat');
        });

        it('returns null when the current page is not the register page', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="login">
                    <form>
                        <input type="text" name="identity">
                        <button type="submit">Login</button>
                    </form>
                </main>
            `;

            const pageElement = document.querySelector('[data-authkit-page]');

            const context = createRegisterTestContext({
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
                <main class="authkit-page authkit-auth-page" data-authkit-page="register"></main>
            `;

            expect(boot(null)).toBeNull();
            expect(boot(undefined)).toBeNull();
            expect(boot('invalid')).toBeNull();
        });
    });
});