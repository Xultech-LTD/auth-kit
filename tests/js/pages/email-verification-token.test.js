/**
 * AuthKit
 * -----------------------------------------------------------------------------
 * File: tests/js/pages/email-verification-token.test.js
 * Author: Michael Erastus
 * Package: AuthKit
 *
 * Description:
 * -----------------------------------------------------------------------------
 * Unit tests for the AuthKit email verification token page runtime module.
 *
 * Responsibilities:
 * - Verify email-verification-token page boot eligibility.
 * - Verify verification form and control discovery.
 * - Verify schema-safe verification control resolution.
 * - Verify OTP-like control detection from rendered attributes.
 * - Verify graceful handling of incomplete page markup.
 */

import { beforeEach, describe, expect, it } from 'vitest';

import {
    boot,
    getContextControls,
    getEmailVerificationTokenForm,
    getEmailVerificationTokenPageElements,
    getFormControls,
    getOtpLikeControls,
    getPrimaryVerificationControl,
    getVerificationControls,
    isCheckboxControl,
    isHiddenControl,
    isOtpLikeControl,
    isPasswordControl,
    isSubmitControl,
    isVisibleFormControl,
} from '../../../resources/js/authkit/pages/email-verification-token.js';


/**
 * Build a minimal AuthKit runtime context for email verification token
 * page-module testing.
 *
 * @param {Object} [overrides={}]
 * @returns {Object}
 */
function createEmailVerificationTokenTestContext(overrides = {}) {
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
                email_verification_token: {
                    enabled: true,
                    pageKey: 'email_verification_token',
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


describe('pages/email-verification-token', () => {
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

            const textInput = document.createElement('input');
            textInput.type = 'text';

            expect(isPasswordControl(passwordInput)).toBe(true);
            expect(isPasswordControl(textInput)).toBe(false);
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

        it('resolves all supported controls from the verification form', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="email_verification_token">
                    <form>
                        <input type="hidden" name="contact" value="user@example.com">
                        <input type="text" name="verification_code" autocomplete="one-time-code" inputmode="numeric">
                        <button type="submit">Verify email</button>
                    </form>
                </main>
            `;

            const form = document.querySelector('form');
            const controls = getFormControls(form);

            expect(controls).toHaveLength(3);
        });

        it('resolves the first form within the current email verification token page', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="email_verification_token">
                    <form id="first-form"></form>
                    <form id="second-form"></form>
                </main>
            `;

            const context = createEmailVerificationTokenTestContext();
            const form = getEmailVerificationTokenForm(context);

            expect(form).not.toBeNull();
            expect(form?.id).toBe('first-form');
        });

        it('returns null when the current email verification token page has no form', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="email_verification_token">
                    <div>No form present.</div>
                </main>
            `;

            const context = createEmailVerificationTokenTestContext();

            expect(getEmailVerificationTokenForm(context)).toBeNull();
        });
    });

    describe('verification control resolution', () => {
        it('resolves visible verification controls safely without assuming field names', () => {
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'contact';

            const checkboxInput = document.createElement('input');
            checkboxInput.type = 'checkbox';
            checkboxInput.name = 'remember_device';

            const passwordInput = document.createElement('input');
            passwordInput.type = 'password';
            passwordInput.name = 'secret';

            const tokenInput = document.createElement('input');
            tokenInput.type = 'text';
            tokenInput.name = 'verification_token';
            tokenInput.setAttribute('autocomplete', 'one-time-code');

            const alternateInput = document.createElement('input');
            alternateInput.type = 'text';
            alternateInput.name = 'backup_token';

            const result = getVerificationControls([
                hiddenInput,
                checkboxInput,
                passwordInput,
                tokenInput,
                alternateInput,
            ]);

            expect(result).toEqual([tokenInput, alternateInput]);
        });

        it('does not assume the verification field is named token', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="email_verification_token">
                    <form>
                        <input type="hidden" name="contact" value="user@example.com">
                        <input type="text" name="verification_code" autocomplete="one-time-code" inputmode="numeric">
                        <button type="submit">Verify email</button>
                    </form>
                </main>
            `;

            const context = createEmailVerificationTokenTestContext();
            const elements = getEmailVerificationTokenPageElements(context);

            expect(elements.verificationControls).toHaveLength(1);
            expect(elements.otpLikeControls).toHaveLength(1);
            expect(elements.primaryVerificationControl?.getAttribute('name')).toBe('verification_code');
        });

        it('returns an empty array when no visible verification control exists', () => {
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';

            const checkboxInput = document.createElement('input');
            checkboxInput.type = 'checkbox';

            const passwordInput = document.createElement('input');
            passwordInput.type = 'password';

            expect(
                getVerificationControls([hiddenInput, checkboxInput, passwordInput])
            ).toEqual([]);
        });

        it('resolves the first visible verification control as the primary one', () => {
            const firstInput = document.createElement('input');
            firstInput.type = 'text';
            firstInput.name = 'verification_code';

            const secondInput = document.createElement('input');
            secondInput.type = 'text';
            secondInput.name = 'backup_code';

            expect(
                getPrimaryVerificationControl([firstInput, secondInput])
            ).toBe(firstInput);
        });

        it('returns null when no primary verification control can be resolved', () => {
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';

            expect(getPrimaryVerificationControl([hiddenInput])).toBeNull();
        });

        it('resolves hidden context controls safely', () => {
            const hiddenA = document.createElement('input');
            hiddenA.type = 'hidden';
            hiddenA.name = 'contact';

            const hiddenB = document.createElement('input');
            hiddenB.type = 'hidden';
            hiddenB.name = 'context';

            const visibleInput = document.createElement('input');
            visibleInput.type = 'text';
            visibleInput.name = 'verification_code';

            expect(getContextControls([hiddenA, visibleInput, hiddenB])).toEqual([
                hiddenA,
                hiddenB,
            ]);
        });

        it('resolves OTP-like controls collection safely', () => {
            const otpInput = document.createElement('input');
            otpInput.type = 'text';
            otpInput.setAttribute('autocomplete', 'one-time-code');

            const numericInput = document.createElement('input');
            numericInput.type = 'text';
            numericInput.setAttribute('inputmode', 'numeric');

            const textInput = document.createElement('input');
            textInput.type = 'text';

            expect(getOtpLikeControls([otpInput, numericInput, textInput])).toEqual([
                otpInput,
                numericInput,
            ]);
        });
    });

    describe('page element discovery', () => {
        it('builds a normalized email verification token page descriptor from rendered markup', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="email_verification_token">
                    <div class="authkit-page-container">
                        <div class="authkit-card">
                            <form method="post" action="/email/verify/token">
                                <input type="hidden" name="contact" value="user@example.com">
                                <input type="text" name="verification_code" autocomplete="one-time-code" inputmode="numeric">
                                <button type="submit">Verify email</button>
                            </form>

                            <div class="authkit-auth-footer">
                                <a href="/email/verify/notice?email=user@example.com">View verification notice</a>
                            </div>
                        </div>
                    </div>
                </main>
            `;

            const context = createEmailVerificationTokenTestContext();
            const elements = getEmailVerificationTokenPageElements(context);

            expect(elements.page).not.toBeNull();
            expect(elements.form).not.toBeNull();

            expect(elements.controls).toHaveLength(3);
            expect(elements.visibleControls).toHaveLength(1);
            expect(elements.hiddenControls).toHaveLength(1);
            expect(elements.checkboxControls).toHaveLength(0);
            expect(elements.passwordControls).toHaveLength(0);
            expect(elements.submitControls).toHaveLength(1);
            expect(elements.links).toHaveLength(1);

            expect(elements.verificationControls).toHaveLength(1);
            expect(elements.otpLikeControls).toHaveLength(1);
            expect(elements.contextControls).toHaveLength(1);

            expect(elements.primaryVerificationControl?.getAttribute('name')).toBe('verification_code');
            expect(elements.contextControls[0]?.getAttribute('name')).toBe('contact');
        });

        it('handles a token page without links safely', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="email_verification_token">
                    <form>
                        <input type="hidden" name="contact" value="user@example.com">
                        <input type="text" name="verification_code" autocomplete="one-time-code">
                        <button type="submit">Verify email</button>
                    </form>
                </main>
            `;

            const context = createEmailVerificationTokenTestContext();
            const elements = getEmailVerificationTokenPageElements(context);

            expect(elements.links).toEqual([]);
            expect(elements.submitControls).toHaveLength(1);
            expect(elements.primaryVerificationControl?.getAttribute('name')).toBe('verification_code');
        });

        it('handles a token page without a form safely', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="email_verification_token">
                    <a href="/email/verify/notice">View verification notice</a>
                </main>
            `;

            const context = createEmailVerificationTokenTestContext();
            const elements = getEmailVerificationTokenPageElements(context);

            expect(elements.form).toBeNull();
            expect(elements.controls).toEqual([]);
            expect(elements.visibleControls).toEqual([]);
            expect(elements.hiddenControls).toEqual([]);
            expect(elements.checkboxControls).toEqual([]);
            expect(elements.passwordControls).toEqual([]);
            expect(elements.submitControls).toEqual([]);
            expect(elements.verificationControls).toEqual([]);
            expect(elements.otpLikeControls).toEqual([]);
            expect(elements.contextControls).toEqual([]);
            expect(elements.primaryVerificationControl).toBeNull();
            expect(elements.links).toHaveLength(1);
        });
    });

    describe('boot', () => {
        it('boots successfully on the email verification token page', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="email_verification_token">
                    <form>
                        <input type="hidden" name="contact" value="user@example.com">
                        <input type="text" name="verification_code" autocomplete="one-time-code">
                        <button type="submit">Verify email</button>
                    </form>
                </main>
            `;

            const context = createEmailVerificationTokenTestContext();
            const result = boot(context);

            expect(result).not.toBeNull();
            expect(result?.key).toBe('email_verification_token');
            expect(result?.form).toBeInstanceOf(HTMLFormElement);
            expect(result?.primaryVerificationControl?.getAttribute('name')).toBe('verification_code');
            expect(result?.contextControls[0]?.getAttribute('name')).toBe('contact');
        });

        it('returns null when the current page is not the email verification token page', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="email_verification_notice">
                    <form>
                        <input type="hidden" name="contact" value="user@example.com">
                        <button type="submit">Resend</button>
                    </form>
                </main>
            `;

            const pageElement = document.querySelector('[data-authkit-page]');

            const context = createEmailVerificationTokenTestContext({
                pageElement,
                page: {
                    key: 'email_verification_notice',
                    pageKey: 'email_verification_notice',
                    element: pageElement,
                    config: {},
                },
            });

            expect(boot(context)).toBeNull();
        });

        it('returns null when the supplied context is invalid', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="email_verification_token"></main>
            `;

            expect(boot(null)).toBeNull();
            expect(boot(undefined)).toBeNull();
            expect(boot('invalid')).toBeNull();
        });
    });
});