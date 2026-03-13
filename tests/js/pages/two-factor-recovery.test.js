/**
 * AuthKit
 * -----------------------------------------------------------------------------
 * File: tests/js/pages/two-factor-recovery.test.js
 * Author: Michael Erastus
 * Package: AuthKit
 *
 * Description:
 * -----------------------------------------------------------------------------
 * Unit tests for the AuthKit two-factor recovery page runtime module.
 *
 * Responsibilities:
 * - Verify two-factor-recovery page boot eligibility.
 * - Verify recovery form and control discovery.
 * - Verify schema-safe recovery control resolution.
 * - Verify hidden context and checkbox control discovery.
 * - Verify graceful handling of incomplete page markup.
 */

import { beforeEach, describe, expect, it } from 'vitest';

import {
    boot,
    getContextControls,
    getFormControls,
    getPrimaryRecoveryControl,
    getRecoveryControls,
    getRememberLikeControls,
    getTwoFactorRecoveryForm,
    getTwoFactorRecoveryPageElements,
    isCheckboxControl,
    isHiddenControl,
    isPasswordControl,
    isSubmitControl,
    isVisibleFormControl,
} from '../../../resources/js/authkit/pages/two-factor-recovery.js';


/**
 * Build a minimal AuthKit runtime context for two-factor recovery page-module
 * testing.
 *
 * @param {Object} [overrides={}]
 * @returns {Object}
 */
function createTwoFactorRecoveryTestContext(overrides = {}) {
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
                two_factor_recovery: {
                    enabled: true,
                    pageKey: 'two_factor_recovery',
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


describe('pages/two-factor-recovery', () => {
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
    });

    describe('form discovery', () => {
        it('returns an empty array when resolving controls from an invalid form', () => {
            expect(getFormControls(null)).toEqual([]);
            expect(getFormControls({})).toEqual([]);
        });

        it('resolves all supported controls from the recovery form', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="two_factor_recovery">
                    <form>
                        <input type="hidden" name="challenge" value="abc123">
                        <input type="text" name="backup_phrase">
                        <input type="checkbox" name="remember_device">
                        <button type="submit">Continue</button>
                    </form>
                </main>
            `;

            const form = document.querySelector('form');
            const controls = getFormControls(form);

            expect(controls).toHaveLength(4);
        });

        it('resolves the first form within the current two-factor recovery page', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="two_factor_recovery">
                    <form id="first-form"></form>
                    <form id="second-form"></form>
                </main>
            `;

            const context = createTwoFactorRecoveryTestContext();
            const form = getTwoFactorRecoveryForm(context);

            expect(form).not.toBeNull();
            expect(form?.id).toBe('first-form');
        });

        it('returns null when the current two-factor recovery page has no form', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="two_factor_recovery">
                    <div>No form present.</div>
                </main>
            `;

            const context = createTwoFactorRecoveryTestContext();

            expect(getTwoFactorRecoveryForm(context)).toBeNull();
        });
    });

    describe('recovery control resolution', () => {
        it('resolves visible recovery controls safely without assuming field names', () => {
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'challenge_ref';

            const checkboxInput = document.createElement('input');
            checkboxInput.type = 'checkbox';
            checkboxInput.name = 'remember_device';

            const passwordInput = document.createElement('input');
            passwordInput.type = 'password';
            passwordInput.name = 'secret';

            const recoveryInput = document.createElement('input');
            recoveryInput.type = 'text';
            recoveryInput.name = 'backup_phrase';

            const alternateRecoveryInput = document.createElement('input');
            alternateRecoveryInput.type = 'text';
            alternateRecoveryInput.name = 'secondary_backup_phrase';

            const result = getRecoveryControls([
                hiddenInput,
                checkboxInput,
                passwordInput,
                recoveryInput,
                alternateRecoveryInput,
            ]);

            expect(result).toEqual([recoveryInput, alternateRecoveryInput]);
        });

        it('does not assume the recovery field is named recovery_code', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="two_factor_recovery">
                    <form>
                        <input type="hidden" name="challenge_ref" value="xyz">
                        <input type="text" name="backup_phrase">
                        <button type="submit">Continue</button>
                    </form>
                </main>
            `;

            const context = createTwoFactorRecoveryTestContext();
            const elements = getTwoFactorRecoveryPageElements(context);

            expect(elements.recoveryControls).toHaveLength(1);
            expect(elements.primaryRecoveryControl?.getAttribute('name')).toBe('backup_phrase');
        });

        it('returns an empty array when no visible recovery control exists', () => {
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';

            const checkboxInput = document.createElement('input');
            checkboxInput.type = 'checkbox';

            const passwordInput = document.createElement('input');
            passwordInput.type = 'password';

            expect(
                getRecoveryControls([hiddenInput, checkboxInput, passwordInput])
            ).toEqual([]);
        });

        it('resolves the first visible recovery control as the primary one', () => {
            const firstInput = document.createElement('input');
            firstInput.type = 'text';
            firstInput.name = 'backup_phrase';

            const secondInput = document.createElement('input');
            secondInput.type = 'text';
            secondInput.name = 'alternate_phrase';

            expect(
                getPrimaryRecoveryControl([firstInput, secondInput])
            ).toBe(firstInput);
        });

        it('returns null when no primary recovery control can be resolved', () => {
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';

            expect(getPrimaryRecoveryControl([hiddenInput])).toBeNull();
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
            visibleInput.name = 'backup_phrase';

            expect(getContextControls([hiddenA, visibleInput, hiddenB])).toEqual([
                hiddenA,
                hiddenB,
            ]);
        });

        it('resolves remember-like checkbox controls safely', () => {
            const checkboxA = document.createElement('input');
            checkboxA.type = 'checkbox';
            checkboxA.name = 'remember_device';

            const checkboxB = document.createElement('input');
            checkboxB.type = 'checkbox';
            checkboxB.name = 'trusted_browser';

            const visibleInput = document.createElement('input');
            visibleInput.type = 'text';
            visibleInput.name = 'backup_phrase';

            expect(getRememberLikeControls([checkboxA, visibleInput, checkboxB])).toEqual([
                checkboxA,
                checkboxB,
            ]);
        });
    });

    describe('page element discovery', () => {
        it('builds a normalized two-factor recovery page descriptor from rendered markup', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="two_factor_recovery">
                    <div class="authkit-page-container">
                        <div class="authkit-card">
                            <form method="post" action="/two-factor-recovery">
                                <input type="hidden" name="challenge_ref" value="abc123">
                                <input type="text" name="backup_phrase">
                                <input type="checkbox" name="remember_device">
                                <button type="submit">Continue</button>
                            </form>

                            <div class="authkit-auth-footer">
                                <a href="/two-factor-challenge?c=abc123">Use authentication code instead</a>
                            </div>
                        </div>
                    </div>
                </main>
            `;

            const context = createTwoFactorRecoveryTestContext();
            const elements = getTwoFactorRecoveryPageElements(context);

            expect(elements.page).not.toBeNull();
            expect(elements.form).not.toBeNull();

            expect(elements.controls).toHaveLength(4);
            expect(elements.visibleControls).toHaveLength(2);
            expect(elements.hiddenControls).toHaveLength(1);
            expect(elements.checkboxControls).toHaveLength(1);
            expect(elements.passwordControls).toHaveLength(0);
            expect(elements.submitControls).toHaveLength(1);
            expect(elements.links).toHaveLength(1);

            expect(elements.recoveryControls).toHaveLength(1);
            expect(elements.contextControls).toHaveLength(1);
            expect(elements.rememberLikeControls).toHaveLength(1);

            expect(elements.primaryRecoveryControl?.getAttribute('name')).toBe('backup_phrase');
            expect(elements.contextControls[0]?.getAttribute('name')).toBe('challenge_ref');
            expect(elements.rememberLikeControls[0]?.getAttribute('name')).toBe('remember_device');
        });

        it('handles a two-factor recovery page without links safely', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="two_factor_recovery">
                    <form>
                        <input type="hidden" name="challenge_ref" value="abc">
                        <input type="text" name="backup_phrase">
                        <button type="submit">Continue</button>
                    </form>
                </main>
            `;

            const context = createTwoFactorRecoveryTestContext();
            const elements = getTwoFactorRecoveryPageElements(context);

            expect(elements.links).toEqual([]);
            expect(elements.submitControls).toHaveLength(1);
            expect(elements.primaryRecoveryControl?.getAttribute('name')).toBe('backup_phrase');
        });

        it('handles a two-factor recovery page without a form safely', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="two_factor_recovery">
                    <a href="/two-factor-challenge">Use authentication code instead</a>
                </main>
            `;

            const context = createTwoFactorRecoveryTestContext();
            const elements = getTwoFactorRecoveryPageElements(context);

            expect(elements.form).toBeNull();
            expect(elements.controls).toEqual([]);
            expect(elements.visibleControls).toEqual([]);
            expect(elements.hiddenControls).toEqual([]);
            expect(elements.checkboxControls).toEqual([]);
            expect(elements.passwordControls).toEqual([]);
            expect(elements.submitControls).toEqual([]);
            expect(elements.recoveryControls).toEqual([]);
            expect(elements.contextControls).toEqual([]);
            expect(elements.rememberLikeControls).toEqual([]);
            expect(elements.primaryRecoveryControl).toBeNull();
            expect(elements.links).toHaveLength(1);
        });
    });

    describe('boot', () => {
        it('boots successfully on the two-factor recovery page', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="two_factor_recovery">
                    <form>
                        <input type="hidden" name="challenge_ref" value="pending">
                        <input type="text" name="backup_phrase">
                        <input type="checkbox" name="remember_device">
                        <button type="submit">Continue</button>
                    </form>
                </main>
            `;

            const context = createTwoFactorRecoveryTestContext();
            const result = boot(context);

            expect(result).not.toBeNull();
            expect(result?.key).toBe('two_factor_recovery');
            expect(result?.form).toBeInstanceOf(HTMLFormElement);
            expect(result?.primaryRecoveryControl?.getAttribute('name')).toBe('backup_phrase');
            expect(result?.contextControls[0]?.getAttribute('name')).toBe('challenge_ref');
            expect(result?.rememberLikeControls[0]?.getAttribute('name')).toBe('remember_device');
        });

        it('returns null when the current page is not the two-factor recovery page', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="two_factor_challenge">
                    <form>
                        <input type="text" name="auth_token">
                        <button type="submit">Verify</button>
                    </form>
                </main>
            `;

            const pageElement = document.querySelector('[data-authkit-page]');

            const context = createTwoFactorRecoveryTestContext({
                pageElement,
                page: {
                    key: 'two_factor_challenge',
                    pageKey: 'two_factor_challenge',
                    element: pageElement,
                    config: {},
                },
            });

            expect(boot(context)).toBeNull();
        });

        it('returns null when the supplied context is invalid', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="two_factor_recovery"></main>
            `;

            expect(boot(null)).toBeNull();
            expect(boot(undefined)).toBeNull();
            expect(boot('invalid')).toBeNull();
        });
    });
});