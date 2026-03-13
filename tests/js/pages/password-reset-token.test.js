/**
 * AuthKit
 * -----------------------------------------------------------------------------
 * File: tests/js/pages/password-reset-token.test.js
 * Author: Michael Erastus
 * Package: AuthKit
 *
 * Description:
 * -----------------------------------------------------------------------------
 * Unit tests for the AuthKit password-reset-token page runtime module.
 *
 * Responsibilities:
 * - Verify password-reset-token page boot eligibility.
 * - Verify reset-token form and control discovery.
 * - Verify schema-safe OTP, password, and context control resolution.
 * - Verify password confirmation discovery by control type/order.
 * - Verify graceful handling of incomplete page markup.
 */

import { beforeEach, describe, expect, it } from 'vitest';

import {
    boot,
    getContextControls,
    getFormControls,
    getOtpLikeControls,
    getPasswordConfirmationControl,
    getPasswordControls,
    getPasswordResetTokenForm,
    getPasswordResetTokenPageElements,
    getPrimaryOtpLikeControl,
    getPrimaryPasswordControl,
    getVisibleResetControls,
    isCheckboxControl,
    isHiddenControl,
    isOtpLikeControl,
    isPasswordControl,
    isSubmitControl,
    isVisibleFormControl,
} from '../../../resources/js/authkit/pages/password-reset-token.js';


/**
 * Build a minimal AuthKit runtime context for password-reset-token page-module
 * testing.
 *
 * @param {Object} [overrides={}]
 * @returns {Object}
 */
function createPasswordResetTokenTestContext(overrides = {}) {
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
                password_reset_token: {
                    enabled: true,
                    pageKey: 'password_reset_token',
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


describe('pages/password-reset-token', () => {
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

        it('detects OTP-like controls from autocomplete, inputmode, or class hooks', () => {
            const autocompleteInput = document.createElement('input');
            autocompleteInput.type = 'text';
            autocompleteInput.setAttribute('autocomplete', 'one-time-code');

            const numericInput = document.createElement('input');
            numericInput.type = 'text';
            numericInput.setAttribute('inputmode', 'numeric');

            const classHookInput = document.createElement('input');
            classHookInput.type = 'text';
            classHookInput.className = 'authkit-otp';

            const normalInput = document.createElement('input');
            normalInput.type = 'text';

            expect(isOtpLikeControl(autocompleteInput)).toBe(true);
            expect(isOtpLikeControl(numericInput)).toBe(true);
            expect(isOtpLikeControl(classHookInput)).toBe(true);
            expect(isOtpLikeControl(normalInput)).toBe(false);
            expect(isOtpLikeControl(null)).toBe(false);
        });
    });

    describe('form discovery', () => {
        it('returns an empty array when resolving controls from an invalid form', () => {
            expect(getFormControls(null)).toEqual([]);
            expect(getFormControls({})).toEqual([]);
        });

        it('resolves all supported controls from the reset-token form', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="password_reset_token">
                    <form>
                        <input type="hidden" name="contact_email" value="user@example.com">
                        <input type="text" name="verification_code" autocomplete="one-time-code" inputmode="numeric">
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

        it('resolves the first form within the current password-reset-token page', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="password_reset_token">
                    <form id="first-form"></form>
                    <form id="second-form"></form>
                </main>
            `;

            const context = createPasswordResetTokenTestContext();
            const form = getPasswordResetTokenForm(context);

            expect(form).not.toBeNull();
            expect(form?.id).toBe('first-form');
        });

        it('returns null when the current password-reset-token page has no form', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="password_reset_token">
                    <div>No form present.</div>
                </main>
            `;

            const context = createPasswordResetTokenTestContext();

            expect(getPasswordResetTokenForm(context)).toBeNull();
        });
    });

    describe('otp, password, and context control resolution', () => {
        it('resolves OTP-like controls by rendered attributes without assuming field names', () => {
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';

            const otpInput = document.createElement('input');
            otpInput.type = 'text';
            otpInput.name = 'verification_code';
            otpInput.setAttribute('autocomplete', 'one-time-code');

            const passwordInput = document.createElement('input');
            passwordInput.type = 'password';
            passwordInput.name = 'new_secret';

            const otpLikeControls = getOtpLikeControls([
                hiddenInput,
                otpInput,
                passwordInput,
            ]);

            expect(otpLikeControls).toEqual([otpInput]);
        });

        it('resolves the first OTP-like control as the primary OTP field', () => {
            const firstOtp = document.createElement('input');
            firstOtp.type = 'text';
            firstOtp.name = 'verification_code';
            firstOtp.setAttribute('autocomplete', 'one-time-code');

            const secondOtp = document.createElement('input');
            secondOtp.type = 'text';
            secondOtp.name = 'backup_code';
            secondOtp.setAttribute('inputmode', 'numeric');

            expect(
                getPrimaryOtpLikeControl([firstOtp, secondOtp])
            ).toBe(firstOtp);
        });

        it('returns null when no primary OTP-like control can be resolved', () => {
            const passwordInput = document.createElement('input');
            passwordInput.type = 'password';

            expect(getPrimaryOtpLikeControl([passwordInput])).toBeNull();
        });

        it('resolves password controls by input type without assuming field names', () => {
            const otpInput = document.createElement('input');
            otpInput.type = 'text';
            otpInput.setAttribute('autocomplete', 'one-time-code');

            const passwordInput = document.createElement('input');
            passwordInput.type = 'password';
            passwordInput.name = 'new_secret';

            const passwordConfirmationInput = document.createElement('input');
            passwordConfirmationInput.type = 'password';
            passwordConfirmationInput.name = 'new_secret_repeat';

            const passwordControls = getPasswordControls([
                otpInput,
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

        it('returns an empty visible-reset-controls collection when only OTP, password, and hidden controls are rendered', () => {
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';

            const otpInput = document.createElement('input');
            otpInput.type = 'text';
            otpInput.setAttribute('autocomplete', 'one-time-code');

            const passwordInput = document.createElement('input');
            passwordInput.type = 'password';

            const passwordConfirmationInput = document.createElement('input');
            passwordConfirmationInput.type = 'password';

            expect(
                getVisibleResetControls([hiddenInput, otpInput, passwordInput, passwordConfirmationInput])
            ).toEqual([]);
        });
    });

    describe('page element discovery', () => {
        it('builds a normalized password-reset-token page descriptor from rendered markup', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="password_reset_token">
                    <div class="authkit-page-container">
                        <div class="authkit-card">
                            <form method="post" action="/reset-password/verify-token">
                                <input type="hidden" name="contact_email" value="user@example.com">
                                <input type="text" name="verification_code" autocomplete="one-time-code" inputmode="numeric">
                                <input type="password" name="new_secret" autocomplete="new-password">
                                <input type="password" name="new_secret_repeat" autocomplete="new-password">
                                <button type="submit">Reset password</button>
                            </form>

                            <div class="authkit-auth-footer">
                                <a href="/forgot-password">Go back</a>
                            </div>
                        </div>
                    </div>
                </main>
            `;

            const context = createPasswordResetTokenTestContext();
            const elements = getPasswordResetTokenPageElements(context);

            expect(elements.page).not.toBeNull();
            expect(elements.form).not.toBeNull();

            expect(elements.controls).toHaveLength(5);
            expect(elements.visibleControls).toHaveLength(3);
            expect(elements.hiddenControls).toHaveLength(1);
            expect(elements.checkboxControls).toHaveLength(0);
            expect(elements.passwordControls).toHaveLength(2);
            expect(elements.submitControls).toHaveLength(1);
            expect(elements.links).toHaveLength(1);

            expect(elements.otpLikeControls).toHaveLength(1);
            expect(elements.primaryOtpLikeControl?.getAttribute('name')).toBe('verification_code');
            expect(elements.visibleResetControls).toEqual([]);
            expect(elements.primaryPasswordControl?.getAttribute('name')).toBe('new_secret');
            expect(elements.passwordConfirmationControl?.getAttribute('name')).toBe('new_secret_repeat');
            expect(elements.contextControls).toHaveLength(1);
        });

        it('handles a reset-token page with additional visible non-password, non-otp controls safely', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="password_reset_token">
                    <form>
                        <input type="hidden" name="contact_email" value="user@example.com">
                        <input type="text" name="verification_code" autocomplete="one-time-code">
                        <input type="text" name="security_hint">
                        <input type="password" name="new_secret">
                        <input type="password" name="new_secret_repeat">
                        <button type="submit">Reset password</button>
                    </form>
                </main>
            `;

            const context = createPasswordResetTokenTestContext();
            const elements = getPasswordResetTokenPageElements(context);

            expect(elements.otpLikeControls).toHaveLength(1);
            expect(elements.visibleResetControls).toHaveLength(1);
            expect(elements.visibleResetControls[0]?.getAttribute('name')).toBe('security_hint');
            expect(elements.primaryPasswordControl?.getAttribute('name')).toBe('new_secret');
        });

        it('handles a reset-token page without links safely', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="password_reset_token">
                    <form>
                        <input type="text" name="verification_code" autocomplete="one-time-code">
                        <input type="password" name="new_secret">
                        <input type="password" name="new_secret_repeat">
                        <button type="submit">Reset password</button>
                    </form>
                </main>
            `;

            const context = createPasswordResetTokenTestContext();
            const elements = getPasswordResetTokenPageElements(context);

            expect(elements.links).toEqual([]);
            expect(elements.submitControls).toHaveLength(1);
            expect(elements.primaryOtpLikeControl?.getAttribute('name')).toBe('verification_code');
            expect(elements.primaryPasswordControl?.getAttribute('name')).toBe('new_secret');
        });

        it('handles a reset-token page without a form safely', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="password_reset_token">
                    <a href="/forgot-password">Go back</a>
                </main>
            `;

            const context = createPasswordResetTokenTestContext();
            const elements = getPasswordResetTokenPageElements(context);

            expect(elements.form).toBeNull();
            expect(elements.controls).toEqual([]);
            expect(elements.visibleControls).toEqual([]);
            expect(elements.hiddenControls).toEqual([]);
            expect(elements.checkboxControls).toEqual([]);
            expect(elements.passwordControls).toEqual([]);
            expect(elements.submitControls).toEqual([]);
            expect(elements.otpLikeControls).toEqual([]);
            expect(elements.primaryOtpLikeControl).toBeNull();
            expect(elements.visibleResetControls).toEqual([]);
            expect(elements.primaryPasswordControl).toBeNull();
            expect(elements.passwordConfirmationControl).toBeNull();
            expect(elements.contextControls).toEqual([]);
            expect(elements.links).toHaveLength(1);
        });
    });

    describe('boot', () => {
        it('boots successfully on the password-reset-token page', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="password_reset_token">
                    <form>
                        <input type="hidden" name="contact_email" value="user@example.com">
                        <input type="text" name="verification_code" autocomplete="one-time-code">
                        <input type="password" name="new_secret">
                        <input type="password" name="new_secret_repeat">
                        <button type="submit">Reset password</button>
                    </form>
                </main>
            `;

            const context = createPasswordResetTokenTestContext();
            const result = boot(context);

            expect(result).not.toBeNull();
            expect(result?.key).toBe('password_reset_token');
            expect(result?.form).toBeInstanceOf(HTMLFormElement);
            expect(result?.primaryOtpLikeControl?.getAttribute('name')).toBe('verification_code');
            expect(result?.primaryPasswordControl?.getAttribute('name')).toBe('new_secret');
            expect(result?.passwordConfirmationControl?.getAttribute('name')).toBe('new_secret_repeat');
            expect(result?.contextControls[0]?.getAttribute('name')).toBe('contact_email');
        });

        it('returns null when the current page is not the password-reset-token page', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="password_reset">
                    <form>
                        <input type="password" name="new_secret">
                        <button type="submit">Reset password</button>
                    </form>
                </main>
            `;

            const pageElement = document.querySelector('[data-authkit-page]');

            const context = createPasswordResetTokenTestContext({
                pageElement,
                page: {
                    key: 'password_reset',
                    pageKey: 'password_reset',
                    element: pageElement,
                    config: {},
                },
            });

            expect(boot(context)).toBeNull();
        });

        it('returns null when the supplied context is invalid', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="password_reset_token"></main>
            `;

            expect(boot(null)).toBeNull();
            expect(boot(undefined)).toBeNull();
            expect(boot('invalid')).toBeNull();
        });
    });
});