/**
 * AuthKit
 * -----------------------------------------------------------------------------
 * File: tests/js/modules/forms/handle-error.test.js
 * Author: Michael Erastus
 * Package: AuthKit
 *
 * Description:
 * -----------------------------------------------------------------------------
 * Unit tests for AuthKit AJAX form error handling utilities.
 *
 * Responsibilities:
 * - Verify field error normalization.
 * - Verify error message resolution.
 * - Verify normalized error result creation.
 * - Verify shared form state mutation on failure.
 * - Verify error event dispatch through context.emit or browser dispatchEvent.
 */

import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('../../../../public/authkit/js/core/events.js', () => ({
    dispatchEvent: vi.fn(),
}));

import { dispatchEvent } from '../../../../public/authkit/js/core/events.js';

import {
    applyErrorState,
    handleError,
    normalizeErrorResult,
    normalizeFieldErrors,
    resolveErrorMessage,
} from '../../../../public/authkit/js/modules/forms/handle-error.js';

import { createFormState } from '../../../../public/authkit/js/modules/forms';


describe('modules/forms/handle-error', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    describe('normalizeFieldErrors', () => {
        it('returns empty object for invalid input', () => {
            expect(normalizeFieldErrors(null)).toEqual({});
            expect(normalizeFieldErrors([])).toEqual({});
            expect(normalizeFieldErrors('invalid')).toEqual({});
        });

        it('normalizes payload.fields object entries', () => {
            const result = normalizeFieldErrors({
                payload: {
                    fields: {
                        email: ['The email field is required.'],
                        password: 'The password is invalid.',
                    },
                },
            });

            expect(result).toEqual({
                email: ['The email field is required.'],
                password: ['The password is invalid.'],
            });
        });

        it('normalizes laravel-style top-level errors object entries', () => {
            const result = normalizeFieldErrors({
                errors: {
                    email: ['The email field is required.'],
                    password: 'The password is invalid.',
                },
            });

            expect(result).toEqual({
                email: ['The email field is required.'],
                password: ['The password is invalid.'],
            });
        });

        it('prefers payload.fields when both payload.fields and top-level errors exist', () => {
            const result = normalizeFieldErrors({
                payload: {
                    fields: {
                        email: ['Payload email error'],
                    },
                },
                errors: {
                    email: ['Top-level email error'],
                    password: ['Top-level password error'],
                },
            });

            expect(result).toEqual({
                email: ['Payload email error'],
                password: ['Top-level password error'],
            });
        });

        it('normalizes structured errors array entries', () => {
            const result = normalizeFieldErrors({
                errors: [
                    { field: 'email', message: 'The email field is required.' },
                    { field: 'email', message: 'The email must be valid.' },
                    { field: 'password', message: 'The password is invalid.' },
                ],
            });

            expect(result).toEqual({
                email: [
                    'The email field is required.',
                    'The email must be valid.',
                ],
                password: ['The password is invalid.'],
            });
        });

        it('ignores invalid field names and blank messages', () => {
            const result = normalizeFieldErrors({
                payload: {
                    fields: {
                        '': ['Should be ignored'],
                        email: ['', 'Valid email message'],
                    },
                },
                errors: [
                    { field: '', message: 'Ignored' },
                    { field: 'password', message: '' },
                    { field: 'password', message: 'Password message' },
                    { field: null, message: 'Ignored' },
                ],
            });

            expect(result).toEqual({
                email: ['Valid email message'],
                password: ['Password message'],
            });
        });

        it('keeps existing object-based field errors and appends structured array errors', () => {
            const result = normalizeFieldErrors({
                payload: {
                    fields: {
                        email: ['Payload email error'],
                    },
                },
                errors: [
                    { field: 'email', message: 'Structured email error' },
                    { field: 'password', message: 'Structured password error' },
                ],
            });

            expect(result).toEqual({
                email: ['Payload email error', 'Structured email error'],
                password: ['Structured password error'],
            });
        });
    });

    describe('resolveErrorMessage', () => {
        it('prefers top-level message when present', () => {
            const result = resolveErrorMessage(
                { message: 'Top level failure.' },
                { email: ['Email is required.'] },
                'Fallback message.'
            );

            expect(result).toBe('Top level failure.');
        });

        it('falls back to first field error when top-level message is absent', () => {
            const result = resolveErrorMessage(
                {},
                {
                    email: ['Email is required.'],
                    password: ['Password is required.'],
                },
                'Fallback message.'
            );

            expect(result).toBe('Email is required.');
        });

        it('falls back to provided fallback when no message is available', () => {
            const result = resolveErrorMessage({}, {}, 'Fallback message.');

            expect(result).toBe('Fallback message.');
        });

        it('uses default fallback when none is provided', () => {
            const result = resolveErrorMessage({}, {});

            expect(result).toBe('Something went wrong.');
        });
    });

    describe('normalizeErrorResult', () => {
        it('builds a normalized error result from response data', () => {
            const responseResult = {
                status: 422,
                data: {
                    message: 'Validation failed.',
                    errors: {
                        email: ['The email field is required.'],
                    },
                },
            };

            const result = normalizeErrorResult(responseResult);

            expect(result).toEqual({
                ok: false,
                status: 422,
                message: 'Validation failed.',
                fieldErrors: {
                    email: ['The email field is required.'],
                },
                data: {
                    message: 'Validation failed.',
                    errors: {
                        email: ['The email field is required.'],
                    },
                },
            });
        });

        it('falls back to data.status when response status is missing', () => {
            const result = normalizeErrorResult({
                data: {
                    status: 401,
                    message: 'Unauthorized.',
                },
            });

            expect(result.status).toBe(401);
            expect(result.message).toBe('Unauthorized.');
        });

        it('falls back to 422 when no response or data status exists', () => {
            const result = normalizeErrorResult({
                data: {},
            });

            expect(result.status).toBe(422);
            expect(result.message).toBe('Something went wrong.');
            expect(result.fieldErrors).toEqual({});
            expect(result.ok).toBe(false);
        });

        it('uses empty object when response data is invalid', () => {
            const result = normalizeErrorResult({
                status: 500,
                data: 'invalid',
            });

            expect(result).toEqual({
                ok: false,
                status: 500,
                message: 'Something went wrong.',
                fieldErrors: {},
                data: {},
            });
        });
    });

    describe('applyErrorState', () => {
        it('applies normalized error result to form state', () => {
            const form = document.createElement('form');
            const formState = createFormState(form);

            formState.submitting = true;
            formState.message = 'Old message';
            formState.fieldErrors = { old: ['Old error'] };
            formState.meta = { previous: true };

            const normalizedResult = {
                ok: false,
                status: 422,
                message: 'Validation failed.',
                fieldErrors: {
                    email: ['The email field is required.'],
                },
                data: {
                    message: 'Validation failed.',
                },
            };

            const result = applyErrorState(formState, normalizedResult);

            expect(result).toBe(formState);
            expect(formState.submitting).toBe(false);
            expect(formState.submitted).toBe(true);
            expect(formState.lastResult).toEqual(normalizedResult);
            expect(formState.message).toBe('Validation failed.');
            expect(formState.fieldErrors).toEqual({
                email: ['The email field is required.'],
            });
            expect(formState.meta).toEqual({
                previous: true,
                status: 422,
                outcome: 'error',
            });
        });

        it('returns the state even when message and field errors are empty', () => {
            const formState = createFormState();

            const normalizedResult = {
                ok: false,
                status: 500,
                message: 'Something went wrong.',
                fieldErrors: {},
                data: {},
            };

            const result = applyErrorState(formState, normalizedResult);

            expect(result).toBe(formState);
            expect(formState.message).toBe('Something went wrong.');
            expect(formState.fieldErrors).toEqual({});
            expect(formState.meta).toEqual({
                status: 500,
                outcome: 'error',
            });
        });
    });

    describe('handleError', () => {
        it('normalizes the response, updates state, and emits through context.emit', () => {
            const form = document.createElement('form');
            const formState = createFormState(form);
            formState.submitting = true;

            const emit = vi.fn();
            const context = { emit };

            const responseResult = {
                status: 422,
                data: {
                    message: 'Validation failed.',
                    errors: {
                        email: ['The email field is required.'],
                    },
                },
            };

            const result = handleError(context, form, formState, responseResult);

            expect(result).toEqual({
                ok: false,
                status: 422,
                message: 'Validation failed.',
                fieldErrors: {
                    email: ['The email field is required.'],
                },
                data: {
                    message: 'Validation failed.',
                    errors: {
                        email: ['The email field is required.'],
                    },
                },
            });

            expect(formState.submitting).toBe(false);
            expect(formState.submitted).toBe(true);
            expect(formState.message).toBe('Validation failed.');
            expect(formState.fieldErrors).toEqual({
                email: ['The email field is required.'],
            });
            expect(formState.meta).toEqual({
                status: 422,
                outcome: 'error',
            });

            expect(emit).toHaveBeenCalledTimes(1);
            expect(emit).toHaveBeenCalledWith('form_error', {
                form,
                status: 422,
                message: 'Validation failed.',
                errors: {
                    email: ['The email field is required.'],
                },
                result: {
                    message: 'Validation failed.',
                    errors: {
                        email: ['The email field is required.'],
                    },
                },
            });

            expect(dispatchEvent).not.toHaveBeenCalled();
        });

        it('falls back to browser dispatchEvent when context.emit is unavailable', () => {
            const form = document.createElement('form');
            const formState = createFormState(form);

            const responseResult = {
                status: 401,
                data: {
                    message: 'Unauthorized.',
                },
            };

            const result = handleError({}, form, formState, responseResult);

            expect(result).toEqual({
                ok: false,
                status: 401,
                message: 'Unauthorized.',
                fieldErrors: {},
                data: {
                    message: 'Unauthorized.',
                },
            });

            expect(dispatchEvent).toHaveBeenCalledTimes(1);
            expect(dispatchEvent).toHaveBeenCalledWith('form_error', {
                form,
                status: 401,
                message: 'Unauthorized.',
                errors: {},
                result: {
                    message: 'Unauthorized.',
                },
            });
        });

        it('uses first field error as message when top-level message is absent', () => {
            const form = document.createElement('form');
            const formState = createFormState(form);

            const result = handleError(null, form, formState, {
                status: 422,
                data: {
                    errors: {
                        email: ['The email field is required.'],
                    },
                },
            });

            expect(result.message).toBe('The email field is required.');
            expect(result.fieldErrors).toEqual({
                email: ['The email field is required.'],
            });
            expect(formState.message).toBe('The email field is required.');
        });

        it('uses default fallback message when response has no message or errors', () => {
            const form = document.createElement('form');
            const formState = createFormState(form);

            const result = handleError(null, form, formState, {
                status: 500,
                data: {},
            });

            expect(result.message).toBe('Something went wrong.');
            expect(result.fieldErrors).toEqual({});
            expect(formState.message).toBe('Something went wrong.');
            expect(formState.meta).toEqual({
                status: 500,
                outcome: 'error',
            });
        });
    });
});