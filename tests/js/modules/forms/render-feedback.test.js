/**
 * AuthKit
 * -----------------------------------------------------------------------------
 * File: tests/js/modules/forms/render-feedback.test.js
 * Author: Michael Erastus
 * Package: AuthKit
 *
 * Description:
 * -----------------------------------------------------------------------------
 * Unit tests for AuthKit AJAX form feedback rendering utilities.
 *
 * Responsibilities:
 * - Verify summary feedback root resolution and creation.
 * - Verify field control and wrapper resolution.
 * - Verify safe clearing of rendered feedback.
 * - Verify summary message and field error rendering.
 * - Verify full form feedback rendering from AuthKit form state.
 */

import { beforeEach, describe, expect, it } from 'vitest';

import {
    ALERT_CLASS,
    FEEDBACK_ROOT_ATTRIBUTE,
    FIELD_ERROR_ATTRIBUTE,
    FORM_ERROR_CLASS,
    FORM_ERRORS_CLASS,
    clearRenderedFeedback,
    escapeSelector,
    getControlErrorClass,
    getFieldControl,
    getFieldWrapper,
    getFormFeedbackRoot,
    renderFieldError,
    renderFieldErrors,
    renderFormFeedback,
    renderSummaryMessage,
} from '../../../../resources/js/authkit/modules/forms/render-feedback.js';


import {
    createFormState,
    setFieldErrors,
    setMessage,
} from '../../../../resources/js/authkit/modules/forms/state.js';


describe('modules/forms/render-feedback', () => {
    beforeEach(() => {
        document.body.innerHTML = '';
    });

    function makeForm() {
        const form = document.createElement('form');

        const emailWrapper = document.createElement('div');
        emailWrapper.setAttribute('data-authkit-field-wrapper', '1');

        const email = document.createElement('input');
        email.name = 'email';
        email.className = 'authkit-input';

        emailWrapper.appendChild(email);

        const tokenWrapper = document.createElement('div');
        tokenWrapper.setAttribute('data-authkit-field-wrapper', '1');

        const token = document.createElement('input');
        token.name = 'token';
        token.className = 'authkit-otp';

        tokenWrapper.appendChild(token);

        const bioWrapper = document.createElement('div');
        bioWrapper.setAttribute('data-authkit-field-wrapper', '1');

        const bio = document.createElement('textarea');
        bio.name = 'bio';
        bio.className = 'authkit-textarea';

        bioWrapper.appendChild(bio);

        const roleWrapper = document.createElement('div');
        roleWrapper.setAttribute('data-authkit-field-wrapper', '1');

        const role = document.createElement('select');
        role.name = 'role';
        role.className = 'authkit-select';

        roleWrapper.appendChild(role);

        form.append(emailWrapper, tokenWrapper, bioWrapper, roleWrapper);

        document.body.appendChild(form);

        return { form, email, token, bio, role, emailWrapper, tokenWrapper };
    }

    it('resolves or creates the form feedback root', () => {
        const { form } = makeForm();

        const first = getFormFeedbackRoot(form);
        const second = getFormFeedbackRoot(form);

        expect(first).toBeInstanceOf(HTMLElement);
        expect(first?.getAttribute(FEEDBACK_ROOT_ATTRIBUTE)).toBe('summary');
        expect(second).toBe(first);
        expect(form.firstElementChild).toBe(first);
    });

    it('resolves a field wrapper from explicit field wrapper marker', () => {
        const { email, emailWrapper } = makeForm();

        expect(getFieldWrapper(email)).toBe(emailWrapper);
    });

    it('resolves field controls by field name', () => {
        const { form, email, token } = makeForm();

        expect(getFieldControl(form, 'email')).toBe(email);
        expect(getFieldControl(form, 'token')).toBe(token);
        expect(getFieldControl(form, 'missing')).toBeNull();
    });

    it('resolves the appropriate control error class', () => {
        const { email, token, bio, role } = makeForm();

        expect(getControlErrorClass(email)).toBe('authkit-input--error');
        expect(getControlErrorClass(token)).toBe('authkit-otp--error');
        expect(getControlErrorClass(bio)).toBe('authkit-textarea--error');
        expect(getControlErrorClass(role)).toBe('authkit-select--error');
    });

    it('renders a summary alert when only a message is present', () => {
        const { form } = makeForm();

        const rendered = renderSummaryMessage(form, 'Login successful.', 'success', {});

        expect(rendered).toBeInstanceOf(HTMLElement);
        expect(rendered?.className).toBe(ALERT_CLASS);
        expect(rendered?.textContent).toBe('Login successful.');
    });

    it('escapes selectors safely when CSS.escape is unavailable', () => {
        const originalCss = globalThis.CSS;

        Object.defineProperty(globalThis, 'CSS', {
            value: undefined,
            configurable: true,
            writable: true,
        });

        expect(escapeSelector('email')).toBe('email');
        expect(escapeSelector('field"name]')).toBe('field\\"name\\]');

        Object.defineProperty(globalThis, 'CSS', {
            value: originalCss,
            configurable: true,
            writable: true,
        });
    });

    it('renders an error summary block when field errors exist', () => {
        const { form } = makeForm();

        const rendered = renderSummaryMessage(form, 'Ignored', 'error', {
            email: ['The email field is required.'],
            token: ['The token field is required.'],
        });

        expect(rendered).toBeInstanceOf(HTMLElement);
        expect(rendered?.className).toBe(FORM_ERRORS_CLASS);
        expect(rendered?.textContent).toContain('Please fix the errors below:');
        expect(rendered?.textContent).toContain('The email field is required.');
        expect(rendered?.textContent).toContain('The token field is required.');
    });

    it('renders a single inline field error and applies error class', () => {
        const { email, emailWrapper } = makeForm();

        const rendered = renderFieldError(email, ['The email field is required.']);

        expect(rendered).toBeInstanceOf(HTMLElement);
        expect(rendered?.className).toBe(FORM_ERROR_CLASS);
        expect(rendered?.getAttribute(FIELD_ERROR_ATTRIBUTE)).toBe('1');
        expect(rendered?.textContent).toBe('The email field is required.');
        expect(email.classList.contains('authkit-input--error')).toBe(true);
        expect(emailWrapper.lastElementChild).toBe(rendered);
    });

    it('renders grouped field errors for matching controls only', () => {
        const { form, email, token } = makeForm();

        const count = renderFieldErrors(form, {
            email: ['The email field is required.'],
            token: ['The token field is required.'],
            missing: ['Should not render.'],
        });

        expect(count).toBe(2);
        expect(email.classList.contains('authkit-input--error')).toBe(true);
        expect(token.classList.contains('authkit-otp--error')).toBe(true);
        expect(form.querySelectorAll(`[${FIELD_ERROR_ATTRIBUTE}]`)).toHaveLength(2);
    });

    it('clears previously rendered summary feedback, field errors, and error classes', () => {
        const { form, email } = makeForm();

        renderSummaryMessage(form, 'Validation failed.', 'error', {});
        renderFieldError(email, ['The email field is required.']);

        expect(form.querySelector(`[${FEEDBACK_ROOT_ATTRIBUTE}="summary"]`)?.innerHTML).not.toBe('');
        expect(form.querySelectorAll(`[${FIELD_ERROR_ATTRIBUTE}]`)).toHaveLength(1);
        expect(email.classList.contains('authkit-input--error')).toBe(true);

        clearRenderedFeedback(form);

        expect(form.querySelector(`[${FEEDBACK_ROOT_ATTRIBUTE}="summary"]`)?.innerHTML).toBe('');
        expect(form.querySelectorAll(`[${FIELD_ERROR_ATTRIBUTE}]`)).toHaveLength(0);
        expect(email.classList.contains('authkit-input--error')).toBe(false);
    });

    it('renders full form feedback from state with field errors taking precedence', () => {
        const { form } = makeForm();
        const state = createFormState(form);

        setMessage(state, 'Validation failed.');
        setFieldErrors(state, {
            email: ['The email field is required.'],
            token: ['The token field is invalid.'],
        });

        renderFormFeedback(form, state, 'error');

        expect(form.querySelector(`.${FORM_ERRORS_CLASS}`)).toBeTruthy();
        expect(form.querySelectorAll(`[${FIELD_ERROR_ATTRIBUTE}]`)).toHaveLength(2);
        expect(form.textContent).toContain('The email field is required.');
        expect(form.textContent).toContain('The token field is invalid.');
    });

    it('renders full form feedback from state with a summary message when no field errors exist', () => {
        const { form } = makeForm();
        const state = createFormState(form);

        setMessage(state, 'Password reset successful.');

        renderFormFeedback(form, state, 'success');

        const alert = form.querySelector(`.${ALERT_CLASS}`);

        expect(alert).toBeTruthy();
        expect(alert?.textContent).toBe('Password reset successful.');
        expect(form.querySelectorAll(`[${FIELD_ERROR_ATTRIBUTE}]`)).toHaveLength(0);
    });
});
