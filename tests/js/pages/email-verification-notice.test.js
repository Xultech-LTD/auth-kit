/**
 * AuthKit
 * -----------------------------------------------------------------------------
 * File: tests/js/pages/email-verification-notice.test.js
 * Author: Michael Erastus
 * Package: AuthKit
 *
 * Description:
 * -----------------------------------------------------------------------------
 * Unit tests for the AuthKit email verification notice page runtime module.
 *
 * Responsibilities:
 * - Verify email-verification-notice page boot eligibility.
 * - Verify resend form and control discovery.
 * - Verify schema-safe hidden context control resolution.
 * - Verify graceful handling of visible controls when schemas change.
 * - Verify graceful handling of incomplete page markup.
 */

import { beforeEach, describe, expect, it } from 'vitest';

import {
    boot,
    getContextControls,
    getEmailVerificationNoticeForm,
    getEmailVerificationNoticePageElements,
    getFormControls,
    getNoticeControls,
    getPrimaryNoticeControl,
    isCheckboxControl,
    isHiddenControl,
    isPasswordControl,
    isSubmitControl,
    isVisibleFormControl,
} from '../../../public/authkit/js/pages/email-verification-notice.js';


/**
 * Build a minimal AuthKit runtime context for email verification notice
 * page-module testing.
 *
 * @param {Object} [overrides={}]
 * @returns {Object}
 */
function createEmailVerificationNoticeTestContext(overrides = {}) {
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
                email_verification_notice: {
                    enabled: true,
                    pageKey: 'email_verification_notice',
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


describe('pages/email-verification-notice', () => {
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

        it('resolves all supported controls from the notice form', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="email_verification_notice">
                    <form>
                        <input type="hidden" name="contact" value="user@example.com">
                        <button type="submit">Resend</button>
                    </form>
                </main>
            `;

            const form = document.querySelector('form');
            const controls = getFormControls(form);

            expect(controls).toHaveLength(2);
        });

        it('resolves the first form within the current email verification notice page', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="email_verification_notice">
                    <form id="first-form"></form>
                    <form id="second-form"></form>
                </main>
            `;

            const context = createEmailVerificationNoticeTestContext();
            const form = getEmailVerificationNoticeForm(context);

            expect(form).not.toBeNull();
            expect(form?.id).toBe('first-form');
        });

        it('returns null when the current email verification notice page has no form', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="email_verification_notice">
                    <div>No form present.</div>
                </main>
            `;

            const context = createEmailVerificationNoticeTestContext();

            expect(getEmailVerificationNoticeForm(context)).toBeNull();
        });
    });

    describe('notice control resolution', () => {
        it('resolves hidden context controls safely', () => {
            const hiddenA = document.createElement('input');
            hiddenA.type = 'hidden';
            hiddenA.name = 'email';

            const hiddenB = document.createElement('input');
            hiddenB.type = 'hidden';
            hiddenB.name = 'context';

            const visibleInput = document.createElement('input');
            visibleInput.type = 'text';
            visibleInput.name = 'identifier';

            expect(getContextControls([hiddenA, visibleInput, hiddenB])).toEqual([
                hiddenA,
                hiddenB,
            ]);
        });

        it('returns an empty notice-controls collection when only hidden context is rendered', () => {
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'email';

            const submitButton = document.createElement('button');
            submitButton.type = 'submit';

            expect(getNoticeControls([hiddenInput, submitButton])).toEqual([]);
        });

        it('does not assume the only form field must remain hidden forever', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="email_verification_notice">
                    <form>
                        <input type="text" name="contact_identifier">
                        <button type="submit">Resend</button>
                    </form>
                </main>
            `;

            const context = createEmailVerificationNoticeTestContext();
            const elements = getEmailVerificationNoticePageElements(context);

            expect(elements.noticeControls).toHaveLength(1);
            expect(elements.primaryNoticeControl?.getAttribute('name')).toBe('contact_identifier');
        });

        it('resolves the first visible notice control as the primary one', () => {
            const firstInput = document.createElement('input');
            firstInput.type = 'text';
            firstInput.name = 'contact_identifier';

            const secondInput = document.createElement('input');
            secondInput.type = 'text';
            secondInput.name = 'alternate_identifier';

            expect(
                getPrimaryNoticeControl([firstInput, secondInput])
            ).toBe(firstInput);
        });

        it('returns null when no primary notice control can be resolved', () => {
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';

            expect(getPrimaryNoticeControl([hiddenInput])).toBeNull();
        });
    });

    describe('page element discovery', () => {
        it('builds a normalized email verification notice page descriptor from rendered markup', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="email_verification_notice">
                    <div class="authkit-page-container">
                        <div class="authkit-card">
                            <form method="post" action="/email/verification-notification">
                                <input type="hidden" name="contact" value="user@example.com">
                                <button type="submit">Resend</button>
                            </form>

                            <div class="authkit-auth-footer">
                                <a href="/email/verify/token?email=user@example.com">Enter verification code</a>
                            </div>
                        </div>
                    </div>
                </main>
            `;

            const context = createEmailVerificationNoticeTestContext();
            const elements = getEmailVerificationNoticePageElements(context);

            expect(elements.page).not.toBeNull();
            expect(elements.form).not.toBeNull();

            expect(elements.controls).toHaveLength(2);
            expect(elements.visibleControls).toHaveLength(0);
            expect(elements.hiddenControls).toHaveLength(1);
            expect(elements.checkboxControls).toHaveLength(0);
            expect(elements.passwordControls).toHaveLength(0);
            expect(elements.submitControls).toHaveLength(1);
            expect(elements.links).toHaveLength(1);

            expect(elements.noticeControls).toEqual([]);
            expect(elements.primaryNoticeControl).toBeNull();
            expect(elements.contextControls).toHaveLength(1);
            expect(elements.contextControls[0]?.getAttribute('name')).toBe('contact');
        });

        it('handles a notice page without links safely', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="email_verification_notice">
                    <form>
                        <input type="hidden" name="contact" value="user@example.com">
                        <button type="submit">Resend</button>
                    </form>
                </main>
            `;

            const context = createEmailVerificationNoticeTestContext();
            const elements = getEmailVerificationNoticePageElements(context);

            expect(elements.links).toEqual([]);
            expect(elements.submitControls).toHaveLength(1);
            expect(elements.contextControls).toHaveLength(1);
        });

        it('handles a notice page without a form safely', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="email_verification_notice">
                    <a href="/email/verify/token">Enter verification code</a>
                </main>
            `;

            const context = createEmailVerificationNoticeTestContext();
            const elements = getEmailVerificationNoticePageElements(context);

            expect(elements.form).toBeNull();
            expect(elements.controls).toEqual([]);
            expect(elements.visibleControls).toEqual([]);
            expect(elements.hiddenControls).toEqual([]);
            expect(elements.checkboxControls).toEqual([]);
            expect(elements.passwordControls).toEqual([]);
            expect(elements.submitControls).toEqual([]);
            expect(elements.noticeControls).toEqual([]);
            expect(elements.primaryNoticeControl).toBeNull();
            expect(elements.contextControls).toEqual([]);
            expect(elements.links).toHaveLength(1);
        });
    });

    describe('boot', () => {
        it('boots successfully on the email verification notice page', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="email_verification_notice">
                    <form>
                        <input type="hidden" name="contact" value="user@example.com">
                        <button type="submit">Resend</button>
                    </form>
                </main>
            `;

            const context = createEmailVerificationNoticeTestContext();
            const result = boot(context);

            expect(result).not.toBeNull();
            expect(result?.key).toBe('email_verification_notice');
            expect(result?.form).toBeInstanceOf(HTMLFormElement);
            expect(result?.contextControls[0]?.getAttribute('name')).toBe('contact');
        });

        it('returns null when the current page is not the email verification notice page', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="email_verification_token">
                    <form>
                        <input type="text" name="token">
                        <button type="submit">Verify</button>
                    </form>
                </main>
            `;

            const pageElement = document.querySelector('[data-authkit-page]');

            const context = createEmailVerificationNoticeTestContext({
                pageElement,
                page: {
                    key: 'email_verification_token',
                    pageKey: 'email_verification_token',
                    element: pageElement,
                    config: {},
                },
            });

            expect(boot(context)).toBeNull();
        });

        it('returns null when the supplied context is invalid', () => {
            document.body.innerHTML = `
                <main class="authkit-page authkit-auth-page" data-authkit-page="email_verification_notice"></main>
            `;

            expect(boot(null)).toBeNull();
            expect(boot(undefined)).toBeNull();
            expect(boot('invalid')).toBeNull();
        });
    });
});