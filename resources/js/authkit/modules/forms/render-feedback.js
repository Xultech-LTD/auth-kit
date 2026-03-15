/**
 * AuthKit
 * -----------------------------------------------------------------------------
 * File: js/modules/forms/render-feedback.js
 * Author: Michael Erastus
 * Package: AuthKit
 *
 * Description:
 * -----------------------------------------------------------------------------
 * DOM feedback rendering utilities for the AuthKit AJAX forms module.
 *
 * This file is responsible for rendering normalized form feedback state into the
 * browser DOM after AJAX submissions.
 *
 * Responsibilities:
 * - Resolve or create a form-level feedback root.
 * - Clear previously rendered feedback for a form.
 * - Render top-level form messages.
 * - Render grouped field validation errors beside matching controls.
 * - Apply and remove error-state classes on invalid controls.
 *
 * Design notes:
 * - This file does not perform HTTP requests.
 * - This file does not normalize server responses.
 * - This file does not mutate AuthKit result contracts directly.
 * - This file only reads normalized form state and reflects it into the DOM.
 *
 * Rendering rules:
 * - Summary feedback is rendered at the top of the form.
 * - Field errors render only the first message per field.
 * - Existing rendered feedback is cleared before a new render pass.
 * - Control error classes are inferred from the rendered control type.
 *
 * @package   AuthKit
 * @author    Michael Erastus
 * @license   MIT
 */

import { addClass, closest, queryAll, queryOne, removeClass } from '../../core/dom.js';
import { normalizeString } from '../../core/helpers.js';
import { getFieldErrors, getMessage } from './state.js';


/**
 * Stable DOM markers and default class hooks used by the feedback renderer.
 */
export const FEEDBACK_ROOT_ATTRIBUTE = 'data-authkit-feedback';
export const FIELD_ERROR_ATTRIBUTE = 'data-authkit-field-error';

export const FORM_ERRORS_CLASS = 'authkit-form-errors';
export const FORM_ERRORS_TITLE_CLASS = 'authkit-form-errors__title';
export const FORM_ERRORS_LIST_CLASS = 'authkit-form-errors__list';
export const FORM_ERRORS_ITEM_CLASS = 'authkit-form-errors__item';

export const FORM_ERROR_CLASS = 'authkit-form-error';
export const ALERT_CLASS = 'authkit-alert';


/**
 * Resolve or create the summary feedback root for a form.
 *
 * The renderer prepends this container to the form when it does not already
 * exist so that AJAX feedback can appear even when the original Blade output
 * contained no server-side errors or alerts.
 *
 * @param {HTMLFormElement|null} form
 * @returns {HTMLElement|null}
 */
export function getFormFeedbackRoot(form) {
    if (!(form instanceof HTMLFormElement)) {
        return null;
    }

    const existing = queryOne(`[${FEEDBACK_ROOT_ATTRIBUTE}="summary"]`, form);

    if (existing instanceof HTMLElement) {
        return existing;
    }

    const root = document.createElement('div');
    root.setAttribute(FEEDBACK_ROOT_ATTRIBUTE, 'summary');

    form.prepend(root);

    return root;
}


/**
 * Resolve the most appropriate field wrapper for inline error rendering.
 *
 * Resolution order:
 * - nearest explicit AuthKit field wrapper
 * - direct parent element fallback
 *
 * @param {Element|null} control
 * @returns {HTMLElement|null}
 */
export function getFieldWrapper(control) {
    if (!(control instanceof Element)) {
        return null;
    }

    const explicitWrapper = closest(control, '[data-authkit-field-wrapper]');

    if (explicitWrapper instanceof HTMLElement) {
        return explicitWrapper;
    }

    return control.parentElement instanceof HTMLElement
        ? control.parentElement
        : null;
}


/**
 * Escape a value for safe CSS selector usage.
 *
 * Uses the native CSS.escape implementation when available and falls back to a
 * conservative string escape for environments such as test runners where CSS
 * may be unavailable.
 *
 * @param {*} value
 * @returns {string}
 */
export function escapeSelector(value) {
    const normalizedValue = normalizeString(value, '');

    if (normalizedValue === '') {
        return '';
    }

    if (
        typeof globalThis.CSS !== 'undefined' &&
        globalThis.CSS !== null &&
        typeof globalThis.CSS.escape === 'function'
    ) {
        return globalThis.CSS.escape(normalizedValue);
    }

    return normalizedValue.replace(/["\\\]]/g, '\\$&');
}


/**
 * Resolve the rendered control for a field name.
 *
 * Supported selectors:
 * - [name="field"]
 * - [name="field[]"]
 *
 * @param {HTMLFormElement|null} form
 * @param {string} fieldName
 * @returns {Element|null}
 */
export function getFieldControl(form, fieldName) {
    if (!(form instanceof HTMLFormElement)) {
        return null;
    }

    const normalizedFieldName = normalizeString(fieldName, '');

    if (normalizedFieldName === '') {
        return null;
    }

    const escapedFieldName = escapeSelector(normalizedFieldName);

    return (
        form.querySelector(`[name="${escapedFieldName}"]`) ||
        form.querySelector(`[name="${escapeSelector(`${normalizedFieldName}[]`)}"]`)
    );
}



/**
 * Resolve the appropriate AuthKit error class for a rendered control.
 *
 * @param {Element|null} control
 * @returns {string|null}
 */
export function getControlErrorClass(control) {
    if (control instanceof HTMLSelectElement) {
        return 'authkit-select--error';
    }

    if (control instanceof HTMLTextAreaElement) {
        return 'authkit-textarea--error';
    }

    if (control instanceof HTMLInputElement) {
        const className = String(control.className || '');

        if (className.includes('authkit-otp')) {
            return 'authkit-otp--error';
        }

        return 'authkit-input--error';
    }

    return null;
}


/**
 * Remove previously rendered feedback from a form.
 *
 * Cleared UI:
 * - summary feedback root content
 * - inline field error elements
 * - rendered control error classes
 *
 * @param {HTMLFormElement|null} form
 * @returns {void}
 */
export function clearRenderedFeedback(form) {
    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    const summaryRoot = queryOne(`[${FEEDBACK_ROOT_ATTRIBUTE}="summary"]`, form);

    if (summaryRoot instanceof HTMLElement) {
        summaryRoot.innerHTML = '';
    }

    queryAll(`[${FIELD_ERROR_ATTRIBUTE}]`, form).forEach((node) => {
        node.remove();
    });

    queryAll(
        '.authkit-input--error, .authkit-otp--error, .authkit-select--error, .authkit-textarea--error',
        form
    ).forEach((control) => {
        removeClass(control, [
            'authkit-input--error',
            'authkit-otp--error',
            'authkit-select--error',
            'authkit-textarea--error',
        ]);
    });
}


/**
 * Render a form-level summary message or error summary block.
 *
 * Rules:
 * - field errors take precedence and render a summary list
 * - otherwise a single alert-style message is rendered when present
 *
 * @param {HTMLFormElement|null} form
 * @param {*} message
 * @param {string} [type='error']
 * @param {Record<string, string[]>} [fieldErrors={}]
 * @returns {HTMLElement|null}
 */
export function renderSummaryMessage(form, message, type = 'error', fieldErrors = {}) {
    const root = getFormFeedbackRoot(form);

    if (!(root instanceof HTMLElement)) {
        return null;
    }

    root.innerHTML = '';

    if (
        fieldErrors &&
        typeof fieldErrors === 'object' &&
        Object.keys(fieldErrors).length > 0
    ) {
        const wrapper = document.createElement('div');
        wrapper.className = FORM_ERRORS_CLASS;

        const title = document.createElement('div');
        title.className = FORM_ERRORS_TITLE_CLASS;
        title.textContent = 'Please fix the errors below:';

        const list = document.createElement('ul');
        list.className = FORM_ERRORS_LIST_CLASS;

        Object.values(fieldErrors)
            .flat()
            .forEach((errorMessage) => {
                const item = document.createElement('li');
                item.className = FORM_ERRORS_ITEM_CLASS;
                item.textContent = String(errorMessage);
                list.appendChild(item);
            });

        wrapper.appendChild(title);
        wrapper.appendChild(list);
        root.appendChild(wrapper);

        return wrapper;
    }

    const normalizedMessage = normalizeString(message, '');

    if (normalizedMessage === '') {
        return null;
    }

    const alert = document.createElement('div');
    alert.className = ALERT_CLASS;
    alert.setAttribute(FEEDBACK_ROOT_ATTRIBUTE, type);
    alert.textContent = normalizedMessage;

    root.appendChild(alert);

    return alert;
}


/**
 * Render a single inline field error beside a control.
 *
 * Only the first field message is rendered inline.
 *
 * @param {Element|null} control
 * @param {string[]} [messages=[]]
 * @returns {HTMLElement|null}
 */
export function renderFieldError(control, messages = []) {
    if (!(control instanceof Element) || !Array.isArray(messages) || messages.length === 0) {
        return null;
    }

    const wrapper = getFieldWrapper(control);

    if (!(wrapper instanceof HTMLElement)) {
        return null;
    }

    const error = document.createElement('div');
    error.className = FORM_ERROR_CLASS;
    error.setAttribute(FIELD_ERROR_ATTRIBUTE, '1');
    error.textContent = String(messages[0]);

    const errorClass = getControlErrorClass(control);

    if (errorClass !== null) {
        addClass(control, errorClass);
    }

    wrapper.appendChild(error);

    return error;
}


/**
 * Render grouped field errors for a form.
 *
 * @param {HTMLFormElement|null} form
 * @param {Record<string, string[]>} [fieldErrors={}]
 * @returns {number}
 */
export function renderFieldErrors(form, fieldErrors = {}) {
    if (!(form instanceof HTMLFormElement) || !fieldErrors || typeof fieldErrors !== 'object') {
        return 0;
    }

    let renderedCount = 0;

    Object.entries(fieldErrors).forEach(([field, messages]) => {
        const control = getFieldControl(form, field);

        if (!control) {
            return;
        }

        if (renderFieldError(control, messages)) {
            renderedCount += 1;
        }
    });

    return renderedCount;
}


/**
 * Render the full current form feedback state into the DOM.
 *
 * Responsibilities:
 * - clear existing rendered feedback
 * - render summary feedback
 * - render inline field errors
 *
 * @param {HTMLFormElement|null} form
 * @param {Object} formState
 * @param {string} [outcome='error']
 * @returns {void}
 */
export function renderFormFeedback(form, formState, outcome = 'error') {
    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    clearRenderedFeedback(form);

    const fieldErrors = getFieldErrors(formState);
    const message = getMessage(formState);

    renderSummaryMessage(form, message, outcome, fieldErrors);
    renderFieldErrors(form, fieldErrors);
}
