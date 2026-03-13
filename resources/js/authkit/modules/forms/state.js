/**
 * AuthKit
 * -----------------------------------------------------------------------------
 * File: js/modules/forms/state.js
 * Author: Michael Erastus
 * Package: AuthKit
 *
 * Description:
 * -----------------------------------------------------------------------------
 * Per-form runtime state utilities for the AuthKit AJAX forms module.
 *
 * This file is responsible for creating and managing the normalized transient
 * state associated with a single bound AuthKit form instance.
 *
 * Responsibilities:
 * - Create a stable per-form state object.
 * - Track submission lifecycle flags.
 * - Track the last normalized AuthKit result payload.
 * - Track grouped field validation errors.
 * - Track a top-level form message for generic feedback rendering.
 * - Provide small mutation helpers so other form modules share one state shape.
 *
 * Design notes:
 * - This file does not perform DOM rendering.
 * - This file does not perform HTTP requests.
 * - This file does not contain page-specific behavior.
 * - State is intentionally lightweight and ephemeral.
 * - Consumers should mutate state through the helpers in this file so the forms
 *   module remains consistent and easy to test.
 *
 * Expected state shape:
 * - form: HTMLFormElement|null
 * - submitting: boolean
 * - submitted: boolean
 * - lastResult: Object|null
 * - fieldErrors: Record<string, string[]>
 * - message: string|null
 * - meta: Record<string, *>
 *
 * @package   AuthKit
 * @author    Michael Erastus
 * @license   MIT
 */

import { isObject, normalizeString } from '../../core/helpers.js';
import {isElement, isHTMLFormElement} from '../../core/dom.js';


/**
 * Create a new normalized runtime state object for a form instance.
 *
 * The returned object is intended to be mutated by the forms runtime during
 * the submission lifecycle of a single form.
 *
 * @param {HTMLFormElement|null} form
 * @returns {{
 *   form: HTMLFormElement|null,
 *   submitting: boolean,
 *   submitted: boolean,
 *   lastResult: Object|null,
 *   fieldErrors: Record<string, string[]>,
 *   message: string|null,
 *   meta: Record<string, *>
 * }}
 */
export function createFormState(form = null) {
    return {
        form: isHTMLFormElement(form) ? form : null,
        submitting: false,
        submitted: false,
        lastResult: null,
        fieldErrors: {},
        message: null,
        meta: {},
    };
}


/**
 * Determine whether a value is a valid AuthKit form state object.
 *
 * This helper is intentionally defensive so mutation helpers can fail safely
 * when provided invalid input.
 *
 * @param {*} state
 * @returns {boolean}
 */
export function isFormState(state) {
    return isObject(state)
        && 'submitting' in state
        && 'submitted' in state
        && 'lastResult' in state
        && 'fieldErrors' in state
        && 'message' in state
        && 'meta' in state;
}


/**
 * Read whether the form is currently in a submitting state.
 *
 * @param {*} state
 * @returns {boolean}
 */
export function isSubmitting(state) {
    if (!isFormState(state)) {
        return false;
    }

    return state.submitting === true;
}


/**
 * Mark the form as currently submitting or idle.
 *
 * When submitting begins:
 * - submitting becomes true
 *
 * When submitting ends:
 * - submitting becomes false
 * - submitted becomes true
 *
 * @param {*} state
 * @param {boolean} submitting
 * @returns {Object|null}
 */
export function setSubmitting(state, submitting = true) {
    if (!isFormState(state)) {
        return null;
    }

    const normalizedSubmitting = submitting === true;

    state.submitting = normalizedSubmitting;

    if (!normalizedSubmitting) {
        state.submitted = true;
    }

    return state;
}


/**
 * Store the last normalized result payload for the form.
 *
 * Invalid result values are normalized to null.
 *
 * @param {*} state
 * @param {Object|null} result
 * @returns {Object|null}
 */
export function setLastResult(state, result = null) {
    if (!isFormState(state)) {
        return null;
    }

    state.lastResult = isObject(result) ? { ...result } : null;

    return state;
}


/**
 * Read the last normalized result payload from state.
 *
 * @param {*} state
 * @returns {Object|null}
 */
export function getLastResult(state) {
    if (!isFormState(state)) {
        return null;
    }

    return isObject(state.lastResult) ? { ...state.lastResult } : null;
}


/**
 * Replace grouped field validation errors in state.
 *
 * Input is normalized into:
 * - Record<string, string[]>
 *
 * Invalid field names are ignored.
 * Empty message values are removed.
 *
 * @param {*} state
 * @param {Object} fieldErrors
 * @returns {Object|null}
 */
export function setFieldErrors(state, fieldErrors = {}) {
    if (!isFormState(state)) {
        return null;
    }

    state.fieldErrors = normalizeFieldErrors(fieldErrors);

    return state;
}


/**
 * Read grouped field validation errors from state.
 *
 * A shallow clone is returned so callers do not mutate state accidentally.
 *
 * @param {*} state
 * @returns {Record<string, string[]>}
 */
export function getFieldErrors(state) {
    if (!isFormState(state)) {
        return {};
    }

    return cloneFieldErrors(state.fieldErrors);
}


/**
 * Determine whether state currently contains any field validation errors.
 *
 * @param {*} state
 * @returns {boolean}
 */
export function hasFieldErrors(state) {
    if (!isFormState(state)) {
        return false;
    }

    return Object.keys(state.fieldErrors).length > 0;
}


/**
 * Clear all grouped field validation errors from state.
 *
 * @param {*} state
 * @returns {Object|null}
 */
export function clearFieldErrors(state) {
    if (!isFormState(state)) {
        return null;
    }

    state.fieldErrors = {};

    return state;
}


/**
 * Store a top-level form message in state.
 *
 * Empty or invalid values are normalized to null.
 *
 * @param {*} state
 * @param {*} message
 * @returns {Object|null}
 */
export function setMessage(state, message = null) {
    if (!isFormState(state)) {
        return null;
    }

    state.message = normalizeString(message, null);

    return state;
}


/**
 * Read the current top-level form message from state.
 *
 * @param {*} state
 * @returns {string|null}
 */
export function getMessage(state) {
    if (!isFormState(state)) {
        return null;
    }

    return normalizeString(state.message, null);
}


/**
 * Clear the current top-level form message from state.
 *
 * @param {*} state
 * @returns {Object|null}
 */
export function clearMessage(state) {
    if (!isFormState(state)) {
        return null;
    }

    state.message = null;

    return state;
}


/**
 * Store arbitrary metadata on the form state.
 *
 * Metadata is shallow-merged into the existing meta bag.
 *
 * @param {*} state
 * @param {Object} meta
 * @returns {Object|null}
 */
export function setMeta(state, meta = {}) {
    if (!isFormState(state)) {
        return null;
    }

    state.meta = {
        ...state.meta,
        ...(isObject(meta) ? meta : {}),
    };

    return state;
}


/**
 * Read state metadata.
 *
 * A shallow clone is returned to avoid accidental external mutation.
 *
 * @param {*} state
 * @returns {Record<string, *>}
 */
export function getMeta(state) {
    if (!isFormState(state)) {
        return {};
    }

    return isObject(state.meta) ? { ...state.meta } : {};
}


/**
 * Clear all transient feedback state while preserving the form reference and
 * general lifecycle markers.
 *
 * Cleared values:
 * - lastResult
 * - fieldErrors
 * - message
 *
 * Preserved values:
 * - form
 * - submitting
 * - submitted
 * - meta
 *
 * @param {*} state
 * @returns {Object|null}
 */
export function clearFeedbackState(state) {
    if (!isFormState(state)) {
        return null;
    }

    state.lastResult = null;
    state.fieldErrors = {};
    state.message = null;

    return state;
}


/**
 * Fully reset the mutable form runtime state.
 *
 * Preserved value:
 * - form
 *
 * Reset values:
 * - submitting => false
 * - submitted => false
 * - lastResult => null
 * - fieldErrors => {}
 * - message => null
 * - meta => {}
 *
 * @param {*} state
 * @returns {Object|null}
 */
export function resetFormState(state) {
    if (!isFormState(state)) {
        return null;
    }

    const form = state.form ?? null;

    state.form = form;
    state.submitting = false;
    state.submitted = false;
    state.lastResult = null;
    state.fieldErrors = {};
    state.message = null;
    state.meta = {};

    return state;
}


/**
 * Normalize grouped field error input into the standard state shape.
 *
 * Supported input:
 * - { email: ['Required'], password: ['Too short'] }
 * - { email: 'Required' }
 *
 * Output:
 * - Record<string, string[]>
 *
 * @param {*} fieldErrors
 * @returns {Record<string, string[]>}
 */
export function normalizeFieldErrors(fieldErrors) {
    if (!isObject(fieldErrors)) {
        return {};
    }

    return Object.entries(fieldErrors).reduce((carry, [field, messages]) => {
        const normalizedField = normalizeString(field, '');

        if (normalizedField === '') {
            return carry;
        }

        const normalizedMessages = Array.isArray(messages)
            ? messages
                .map((message) => normalizeString(message, ''))
                .filter((message) => message !== '')
            : [normalizeString(messages, '')].filter((message) => message !== '');

        if (normalizedMessages.length === 0) {
            return carry;
        }

        carry[normalizedField] = normalizedMessages;

        return carry;
    }, {});
}


/**
 * Clone grouped field errors safely.
 *
 * @param {*} fieldErrors
 * @returns {Record<string, string[]>}
 */
export function cloneFieldErrors(fieldErrors) {
    const normalized = normalizeFieldErrors(fieldErrors);

    return Object.entries(normalized).reduce((carry, [field, messages]) => {
        carry[field] = [...messages];
        return carry;
    }, {});
}