/**
 * AuthKit
 * -----------------------------------------------------------------------------
 * File: tests/js/pages/two-factor-challenge.test.js
 * Author: Michael Erastus
 * Package: AuthKit
 *
 * Description:
 * -----------------------------------------------------------------------------
 * Unit tests for the AuthKit two-factor challenge page runtime module.
 *
 * Responsibilities:
 * - Verify two-factor-challenge page boot eligibility.
 * - Verify challenge form and control discovery.
 * - Verify schema-safe verification control resolution.
 * - Verify OTP-like control detection from rendered attributes.
 * - Verify graceful handling of incomplete page markup.
 */

import { beforeEach, describe, expect, it } from 'vitest';

import {
    boot,
    getContextControls,
    getFormControls,
    getOtpLikeControls,
    getPrimaryVerificationControl,
    getTwoFactorChallengeForm,
    getTwoFactorChallengePageElements,
    getVerificationControls,
    isCheckboxControl,
    isHiddenControl,
    isOtpLikeControl,
    isPasswordControl,
    isSubmitControl,
    isVisibleFormControl,
} from '../../../public/authkit/js/pages/two-factor-challenge.js';


/**
 * Build a minimal AuthKit runtime context for two-factor challenge page-module
 * testing.
 *
 * @param {Object} [overrides={}]
 * @returns {Object}
 */
function createTwoFactorChallengeTestContext(overrides = {}) {
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
                two_factor_challenge: {
                    enabled: true,
                    pageKey: 'two_factor_challenge',
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


describe('pages/two-factor-challenge', () => {
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

        it('resolves all supported controls from the challenge form', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="two_factor_challenge">
                    <form>
                        <input type="hidden" name="challenge" value="abc123">
                        <input type="text" name="verification_code" autocomplete="one-time-code" inputmode="numeric">
                        <button type="submit">Verify</button>
                    </form>
                </main>
            `;

            const form = document.querySelector('form');
            const controls = getFormControls(form);

            expect(controls).toHaveLength(3);
        });

        it('resolves the first form within the current two-factor challenge page', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="two_factor_challenge">
                    <form id="first-form"></form>
                    <form id="second-form"></form>
                </main>
            `;

            const context = createTwoFactorChallengeTestContext();
            const form = getTwoFactorChallengeForm(context);

            expect(form).not.toBeNull();
            expect(form?.id).toBe('first-form');
        });

        it('returns null when the current two-factor challenge page has no form', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="two_factor_challenge">
                    <div>No form present.</div>
                </main>
            `;

            const context = createTwoFactorChallengeTestContext();

            expect(getTwoFactorChallengeForm(context)).toBeNull();
        });
    });

    describe('verification control resolution', () => {
        it('resolves visible verification controls safely without assuming field names', () => {
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'challenge_ref';

            const checkboxInput = document.createElement('input');
            checkboxInput.type = 'checkbox';
            checkboxInput.name = 'remember_device';

            const passwordInput = document.createElement('input');
            passwordInput.type = 'password';
            passwordInput.name = 'secret';

            const otpInput = document.createElement('input');
            otpInput.type = 'text';
            otpInput.name = 'totp';
            otpInput.setAttribute('autocomplete', 'one-time-code');

            const backupInput = document.createElement('input');
            backupInput.type = 'text';
            backupInput.name = 'backup_code';

            const result = getVerificationControls([
                hiddenInput,
                checkboxInput,
                passwordInput,
                otpInput,
                backupInput,
            ]);

            expect(result).toEqual([otpInput, backupInput]);
        });

        it('resolves OTP-like controls safely without assuming the control name is code', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="two_factor_challenge">
                    <form>
                        <input type="hidden" name="pending_ref" value="xyz">
                        <input type="text" name="totp_token" autocomplete="one-time-code" inputmode="numeric">
                        <button type="submit">Verify</button>
                    </form>
                </main>
            `;

            const context = createTwoFactorChallengeTestContext();
            const elements = getTwoFactorChallengePageElements(context);

            expect(elements.otpLikeControls).toHaveLength(1);
            expect(elements.otpLikeControls[0]?.getAttribute('name')).toBe('totp_token');
            expect(elements.primaryVerificationControl?.getAttribute('name')).toBe('totp_token');
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
            firstInput.name = 'totp_token';

            const secondInput = document.createElement('input');
            secondInput.type = 'text';
            secondInput.name = 'fallback_token';

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
            hiddenA.name = 'challenge_a';

            const hiddenB = document.createElement('input');
            hiddenB.type = 'hidden';
            hiddenB.name = 'challenge_b';

            const visibleInput = document.createElement('input');
            visibleInput.type = 'text';
            visibleInput.name = 'totp';

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
        it('builds a normalized two-factor challenge page descriptor from rendered markup', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="two_factor_challenge">
                    <div class="authkit-page-container">
                        <div class="authkit-card">
                            <form method="post" action="/two-factor-challenge">
                                <input type="hidden" name="challenge_ref" value="abc123">
                                <input type="text" name="totp_token" autocomplete="one-time-code" inputmode="numeric">
                                <button type="submit">Verify</button>

                                <div class="authkit-auth-footer">
                                    <a href="/two-factor-recovery">Use a recovery code</a>
                                </div>
                            </form>
                        </div>
                    </div>
                </main>
            `;

            const context = createTwoFactorChallengeTestContext();
            const elements = getTwoFactorChallengePageElements(context);

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

            expect(elements.primaryVerificationControl?.getAttribute('name')).toBe('totp_token');
            expect(elements.contextControls[0]?.getAttribute('name')).toBe('challenge_ref');
        });

        it('handles a two-factor challenge page without links safely', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="two_factor_challenge">
                    <form>
                        <input type="hidden" name="pending_ref" value="abc">
                        <input type="text" name="verification_token" autocomplete="one-time-code">
                        <button type="submit">Verify</button>
                    </form>
                </main>
            `;

            const context = createTwoFactorChallengeTestContext();
            const elements = getTwoFactorChallengePageElements(context);

            expect(elements.links).toEqual([]);
            expect(elements.submitControls).toHaveLength(1);
            expect(elements.primaryVerificationControl?.getAttribute('name')).toBe('verification_token');
        });

        it('handles a two-factor challenge page without a form safely', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="two_factor_challenge">
                    <a href="/two-factor-recovery">Use a recovery code</a>
                </main>
            `;

            const context = createTwoFactorChallengeTestContext();
            const elements = getTwoFactorChallengePageElements(context);

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
        it('boots successfully on the two-factor challenge page', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="two_factor_challenge">
                    <form>
                        <input type="hidden" name="challenge_ref" value="pending">
                        <input type="text" name="auth_token" autocomplete="one-time-code">
                        <button type="submit">Verify</button>
                    </form>
                </main>
            `;

            const context = createTwoFactorChallengeTestContext();
            const result = boot(context);

            expect(result).not.toBeNull();
            expect(result?.key).toBe('two_factor_challenge');
            expect(result?.form).toBeInstanceOf(HTMLFormElement);
            expect(result?.primaryVerificationControl?.getAttribute('name')).toBe('auth_token');
            expect(result?.contextControls[0]?.getAttribute('name')).toBe('challenge_ref');
        });

        it('returns null when the current page is not the two-factor challenge page', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="two_factor_recovery">
                    <form>
                        <input type="text" name="recovery_code">
                        <button type="submit">Continue</button>
                    </form>
                </main>
            `;

            const pageElement = document.querySelector('[data-authkit-page]');

            const context = createTwoFactorChallengeTestContext({
                pageElement,
                page: {
                    key: 'two_factor_recovery',
                    pageKey: 'two_factor_recovery',
                    element: pageElement,
                    config: {},
                },
            });

            expect(boot(context)).toBeNull();
        });

        it('returns null when the supplied context is invalid', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="two_factor_challenge"></main>
            `;

            expect(boot(null)).toBeNull();
            expect(boot(undefined)).toBeNull();
            expect(boot('invalid')).toBeNull();
        });
    });
});