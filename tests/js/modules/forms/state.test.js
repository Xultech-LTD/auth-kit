/**
 * AuthKit
 * -----------------------------------------------------------------------------
 * File: tests/js/modules/forms/state.test.js
 * Author: Michael Erastus
 * Package: AuthKit
 *
 * Description:
 * -----------------------------------------------------------------------------
 * Unit tests for AuthKit AJAX form runtime state utilities.
 *
 * Responsibilities:
 * - Verify state creation and validation.
 * - Verify submission lifecycle mutations.
 * - Verify result, message, field error, and meta helpers.
 * - Verify feedback clearing and full reset behavior.
 * - Verify field error normalization and cloning helpers.
 */

import { beforeEach, describe, expect, it } from 'vitest';

import {
    clearFeedbackState,
    clearFieldErrors,
    clearMessage,
    cloneFieldErrors,
    createFormState,
    getFieldErrors,
    getLastResult,
    getMessage,
    getMeta,
    hasFieldErrors,
    isFormState,
    isSubmitting,
    normalizeFieldErrors,
    resetFormState,
    setFieldErrors,
    setLastResult,
    setMessage,
    setMeta,
    setSubmitting,
} from '../../../../resources/js/authkit/modules/forms/state.js';


describe('modules/forms/state', () => {
    beforeEach(() => {
        document.body.innerHTML = '';
    });

    describe('createFormState', () => {
        it('creates a normalized form state for a valid form', () => {
            const form = document.createElement('form');

            const state = createFormState(form);

            expect(state).toEqual({
                form,
                submitting: false,
                submitted: false,
                lastResult: null,
                fieldErrors: {},
                message: null,
                meta: {},
            });
        });

        it('creates a normalized form state with null form for invalid input', () => {
            const stateA = createFormState(null);
            const stateB = createFormState(document.createElement('div'));

            expect(stateA.form).toBeNull();
            expect(stateB.form).toBeNull();
            expect(stateA.submitting).toBe(false);
            expect(stateA.submitted).toBe(false);
            expect(stateA.lastResult).toBeNull();
            expect(stateA.fieldErrors).toEqual({});
            expect(stateA.message).toBeNull();
            expect(stateA.meta).toEqual({});
        });
    });

    describe('isFormState', () => {
        it('returns true for a valid form state object', () => {
            const state = createFormState();

            expect(isFormState(state)).toBe(true);
        });

        it('returns false for invalid values', () => {
            expect(isFormState(null)).toBe(false);
            expect(isFormState([])).toBe(false);
            expect(isFormState('invalid')).toBe(false);
            expect(isFormState({})).toBe(false);
            expect(isFormState({ submitting: false })).toBe(false);
        });
    });

    describe('isSubmitting and setSubmitting', () => {
        it('reads whether a valid form state is submitting', () => {
            const state = createFormState();

            expect(isSubmitting(state)).toBe(false);

            state.submitting = true;

            expect(isSubmitting(state)).toBe(true);
        });

        it('returns false for invalid state input', () => {
            expect(isSubmitting(null)).toBe(false);
            expect(isSubmitting({})).toBe(false);
        });

        it('sets submitting to true without marking as submitted', () => {
            const state = createFormState();

            const result = setSubmitting(state, true);

            expect(result).toBe(state);
            expect(state.submitting).toBe(true);
            expect(state.submitted).toBe(false);
        });

        it('sets submitting to false and marks state as submitted', () => {
            const state = createFormState();
            state.submitting = true;

            const result = setSubmitting(state, false);

            expect(result).toBe(state);
            expect(state.submitting).toBe(false);
            expect(state.submitted).toBe(true);
        });

        it('returns null for invalid state input', () => {
            expect(setSubmitting(null, true)).toBeNull();
            expect(setSubmitting({}, true)).toBeNull();
        });
    });

    describe('setLastResult and getLastResult', () => {
        it('stores a shallow cloned last result object', () => {
            const state = createFormState();
            const resultPayload = {
                ok: true,
                message: 'Success',
            };

            const result = setLastResult(state, resultPayload);

            expect(result).toBe(state);
            expect(state.lastResult).toEqual(resultPayload);
            expect(state.lastResult).not.toBe(resultPayload);
        });

        it('normalizes invalid last result values to null', () => {
            const state = createFormState();
            state.lastResult = { old: true };

            setLastResult(state, null);
            expect(state.lastResult).toBeNull();

            setLastResult(state, 'invalid');
            expect(state.lastResult).toBeNull();
        });

        it('returns a shallow clone of the last result', () => {
            const state = createFormState();

            setLastResult(state, {
                ok: false,
                message: 'Validation failed',
            });

            const result = getLastResult(state);

            expect(result).toEqual({
                ok: false,
                message: 'Validation failed',
            });

            expect(result).not.toBe(state.lastResult);
        });

        it('returns null for invalid state or missing last result', () => {
            expect(getLastResult(null)).toBeNull();
            expect(getLastResult({})).toBeNull();

            const state = createFormState();
            expect(getLastResult(state)).toBeNull();
        });
    });

    describe('normalizeFieldErrors and cloneFieldErrors', () => {
        it('normalizes grouped field errors into the standard shape', () => {
            const result = normalizeFieldErrors({
                email: ['Required', 'Must be valid'],
                password: 'Too short',
            });

            expect(result).toEqual({
                email: ['Required', 'Must be valid'],
                password: ['Too short'],
            });
        });

        it('ignores invalid field names and blank messages', () => {
            const result = normalizeFieldErrors({
                '': ['Ignored'],
                email: ['', 'Required', '   '],
                password: null,
                token: ['abc', '', 'def'],
            });

            expect(result).toEqual({
                email: ['Required'],
                token: ['abc', 'def'],
            });
        });

        it('returns empty object for invalid field error input', () => {
            expect(normalizeFieldErrors(null)).toEqual({});
            expect(normalizeFieldErrors([])).toEqual({});
            expect(normalizeFieldErrors('invalid')).toEqual({});
        });

        it('clones grouped field errors safely', () => {
            const source = {
                email: ['Required'],
                password: ['Too short'],
            };

            const cloned = cloneFieldErrors(source);

            expect(cloned).toEqual(source);
            expect(cloned).not.toBe(source);
            expect(cloned.email).not.toBe(source.email);
            expect(cloned.password).not.toBe(source.password);
        });

        it('returns normalized empty object when cloning invalid input', () => {
            expect(cloneFieldErrors(null)).toEqual({});
            expect(cloneFieldErrors('invalid')).toEqual({});
        });
    });

    describe('field error helpers', () => {
        it('stores normalized field errors in state', () => {
            const state = createFormState();

            const result = setFieldErrors(state, {
                email: ['Required'],
                password: 'Too short',
            });

            expect(result).toBe(state);
            expect(state.fieldErrors).toEqual({
                email: ['Required'],
                password: ['Too short'],
            });
        });

        it('returns cloned field errors from state', () => {
            const state = createFormState();

            setFieldErrors(state, {
                email: ['Required'],
            });

            const errors = getFieldErrors(state);

            expect(errors).toEqual({
                email: ['Required'],
            });
            expect(errors).not.toBe(state.fieldErrors);
            expect(errors.email).not.toBe(state.fieldErrors.email);
        });

        it('returns empty object for invalid state in getFieldErrors', () => {
            expect(getFieldErrors(null)).toEqual({});
            expect(getFieldErrors({})).toEqual({});
        });

        it('detects whether state has field errors', () => {
            const state = createFormState();

            expect(hasFieldErrors(state)).toBe(false);

            setFieldErrors(state, {
                email: ['Required'],
            });

            expect(hasFieldErrors(state)).toBe(true);
        });

        it('returns false for invalid state in hasFieldErrors', () => {
            expect(hasFieldErrors(null)).toBe(false);
            expect(hasFieldErrors({})).toBe(false);
        });

        it('clears grouped field errors from state', () => {
            const state = createFormState();

            setFieldErrors(state, {
                email: ['Required'],
            });

            const result = clearFieldErrors(state);

            expect(result).toBe(state);
            expect(state.fieldErrors).toEqual({});
        });

        it('returns null when clearing field errors on invalid state', () => {
            expect(clearFieldErrors(null)).toBeNull();
            expect(clearFieldErrors({})).toBeNull();
        });
    });

    describe('message helpers', () => {
        it('stores a normalized top-level message in state', () => {
            const state = createFormState();

            const result = setMessage(state, '  Hello world  ');

            expect(result).toBe(state);
            expect(state.message).toBe('Hello world');
        });

        it('normalizes invalid or blank messages to null', () => {
            const state = createFormState();

            setMessage(state, '');
            expect(state.message).toBeNull();

            setMessage(state, '   ');
            expect(state.message).toBeNull();

            setMessage(state, null);
            expect(state.message).toBeNull();
        });

        it('reads the current top-level message from state', () => {
            const state = createFormState();

            setMessage(state, 'Success message');

            expect(getMessage(state)).toBe('Success message');
        });

        it('returns null for invalid state in getMessage', () => {
            expect(getMessage(null)).toBeNull();
            expect(getMessage({})).toBeNull();
        });

        it('clears the current top-level message from state', () => {
            const state = createFormState();

            setMessage(state, 'To be cleared');

            const result = clearMessage(state);

            expect(result).toBe(state);
            expect(state.message).toBeNull();
        });

        it('returns null when clearing message on invalid state', () => {
            expect(clearMessage(null)).toBeNull();
            expect(clearMessage({})).toBeNull();
        });
    });

    describe('meta helpers', () => {
        it('stores metadata by shallow merging into existing meta bag', () => {
            const state = createFormState();
            state.meta = { existing: true, count: 1 };

            const result = setMeta(state, {
                count: 2,
                status: 200,
            });

            expect(result).toBe(state);
            expect(state.meta).toEqual({
                existing: true,
                count: 2,
                status: 200,
            });
        });

        it('ignores invalid meta input and preserves existing meta', () => {
            const state = createFormState();
            state.meta = { existing: true };

            setMeta(state, null);
            expect(state.meta).toEqual({ existing: true });

            setMeta(state, 'invalid');
            expect(state.meta).toEqual({ existing: true });
        });

        it('returns cloned metadata from state', () => {
            const state = createFormState();

            setMeta(state, {
                status: 422,
                outcome: 'error',
            });

            const meta = getMeta(state);

            expect(meta).toEqual({
                status: 422,
                outcome: 'error',
            });
            expect(meta).not.toBe(state.meta);
        });

        it('returns empty object for invalid state in getMeta', () => {
            expect(getMeta(null)).toEqual({});
            expect(getMeta({})).toEqual({});
        });

        it('returns null for invalid state in setMeta', () => {
            expect(setMeta(null, {})).toBeNull();
            expect(setMeta({}, {})).toBeNull();
        });
    });

    describe('clearFeedbackState', () => {
        it('clears transient feedback while preserving form, lifecycle flags, and meta', () => {
            const form = document.createElement('form');
            const state = createFormState(form);

            state.submitting = true;
            state.submitted = true;
            state.lastResult = { ok: false };
            state.fieldErrors = { email: ['Required'] };
            state.message = 'Validation failed';
            state.meta = { outcome: 'error' };

            const result = clearFeedbackState(state);

            expect(result).toBe(state);
            expect(state.form).toBe(form);
            expect(state.submitting).toBe(true);
            expect(state.submitted).toBe(true);
            expect(state.lastResult).toBeNull();
            expect(state.fieldErrors).toEqual({});
            expect(state.message).toBeNull();
            expect(state.meta).toEqual({ outcome: 'error' });
        });

        it('returns null for invalid state input', () => {
            expect(clearFeedbackState(null)).toBeNull();
            expect(clearFeedbackState({})).toBeNull();
        });
    });

    describe('resetFormState', () => {
        it('fully resets mutable form runtime state while preserving form reference', () => {
            const form = document.createElement('form');
            const state = createFormState(form);

            state.submitting = true;
            state.submitted = true;
            state.lastResult = { ok: true };
            state.fieldErrors = { email: ['Required'] };
            state.message = 'Old message';
            state.meta = { status: 200 };

            const result = resetFormState(state);

            expect(result).toBe(state);
            expect(state.form).toBe(form);
            expect(state.submitting).toBe(false);
            expect(state.submitted).toBe(false);
            expect(state.lastResult).toBeNull();
            expect(state.fieldErrors).toEqual({});
            expect(state.message).toBeNull();
            expect(state.meta).toEqual({});
        });

        it('returns null for invalid state input', () => {
            expect(resetFormState(null)).toBeNull();
            expect(resetFormState({})).toBeNull();
        });
    });
});