/**
 * AuthKit
 * -----------------------------------------------------------------------------
 * File: tests/js/modules/forms/handle-success.test.js
 * Author: Michael Erastus
 * Package: AuthKit
 *
 * Description:
 * -----------------------------------------------------------------------------
 * Unit tests for AuthKit AJAX form success handling utilities.
 *
 * Responsibilities:
 * - Verify success message resolution.
 * - Verify success redirect resolution.
 * - Verify normalized success result creation.
 * - Verify shared form state mutation on success.
 * - Verify success event dispatch through context.emit or browser dispatchEvent.
 */

import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('../../../../resources/js/authkit/core/events.js', () => ({
    dispatchEvent: vi.fn(),
}));

import { dispatchEvent } from '../../../../resources/js/authkit/core/events.js';

import {
    applySuccessState,
    handleSuccess,
    normalizeSuccessResult,
    resolveSuccessMessage,
    resolveSuccessRedirectUrl,
} from '../../../../resources/js/authkit/modules/forms/handle-success.js';

import { createFormState } from '../../../../resources/js/authkit/modules/forms';


describe('modules/forms/handle-success', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    describe('resolveSuccessRedirectUrl', () => {
        it('returns redirect.url when present', () => {
            const result = resolveSuccessRedirectUrl({
                redirect: {
                    url: '/dashboard',
                },
            });

            expect(result).toBe('/dashboard');
        });

        it('returns null when redirect.url is missing', () => {
            expect(resolveSuccessRedirectUrl({})).toBeNull();
            expect(resolveSuccessRedirectUrl({ redirect: {} })).toBeNull();
            expect(resolveSuccessRedirectUrl(null)).toBeNull();
        });

        it('returns null when redirect.url is blank or invalid', () => {
            expect(resolveSuccessRedirectUrl({
                redirect: {
                    url: '',
                },
            })).toBeNull();

            expect(resolveSuccessRedirectUrl({
                redirect: {
                    url: '   ',
                },
            })).toBeNull();

            expect(resolveSuccessRedirectUrl({
                redirect: {
                    url: 123,
                },
            })).toBeNull();
        });
    });

    describe('resolveSuccessMessage', () => {
        it('returns top-level message when present', () => {
            const result = resolveSuccessMessage({
                message: 'Operation completed successfully.',
            });

            expect(result).toBe('Operation completed successfully.');
        });

        it('returns fallback when message is missing', () => {
            const result = resolveSuccessMessage({}, 'Fallback success.');

            expect(result).toBe('Fallback success.');
        });

        it('returns default fallback when message is invalid and no fallback is provided', () => {
            expect(resolveSuccessMessage({})).toBe('Operation completed.');
            expect(resolveSuccessMessage({ message: '' })).toBe('Operation completed.');
            expect(resolveSuccessMessage({ message: '   ' })).toBe('Operation completed.');
            expect(resolveSuccessMessage({ message: null })).toBe('Operation completed.');
        });
    });

    describe('normalizeSuccessResult', () => {
        it('builds a normalized success result from response data', () => {
            const responseResult = {
                status: 200,
                data: {
                    message: 'Login successful.',
                    redirect: {
                        url: '/dashboard',
                    },
                },
            };

            const result = normalizeSuccessResult(responseResult);

            expect(result).toEqual({
                ok: true,
                status: 200,
                message: 'Login successful.',
                redirectUrl: '/dashboard',
                data: {
                    message: 'Login successful.',
                    redirect: {
                        url: '/dashboard',
                    },
                },
            });
        });

        it('falls back to data.status when response status is missing', () => {
            const result = normalizeSuccessResult({
                data: {
                    status: 201,
                    message: 'Created successfully.',
                },
            });

            expect(result).toEqual({
                ok: true,
                status: 201,
                message: 'Created successfully.',
                redirectUrl: null,
                data: {
                    status: 201,
                    message: 'Created successfully.',
                },
            });
        });

        it('falls back to 200 when no response or data status exists', () => {
            const result = normalizeSuccessResult({
                data: {},
            });

            expect(result).toEqual({
                ok: true,
                status: 200,
                message: 'Operation completed.',
                redirectUrl: null,
                data: {},
            });
        });

        it('uses empty object when response data is invalid', () => {
            const result = normalizeSuccessResult({
                status: 204,
                data: 'invalid',
            });

            expect(result).toEqual({
                ok: true,
                status: 204,
                message: 'Operation completed.',
                redirectUrl: null,
                data: {},
            });
        });
    });

    describe('applySuccessState', () => {
        it('applies normalized success result to form state', () => {
            const form = document.createElement('form');
            const formState = createFormState(form);

            formState.submitting = true;
            formState.message = 'Old error';
            formState.fieldErrors = {
                email: ['The email field is required.'],
            };
            formState.meta = { previous: true };

            const normalizedResult = {
                ok: true,
                status: 200,
                message: 'Login successful.',
                redirectUrl: '/dashboard',
                data: {
                    message: 'Login successful.',
                    redirect: {
                        url: '/dashboard',
                    },
                },
            };

            const result = applySuccessState(formState, normalizedResult);

            expect(result).toBe(formState);
            expect(formState.submitting).toBe(false);
            expect(formState.submitted).toBe(true);
            expect(formState.lastResult).toEqual(normalizedResult);
            expect(formState.fieldErrors).toEqual({});
            expect(formState.message).toBe('Login successful.');
            expect(formState.meta).toEqual({
                previous: true,
                status: 200,
                outcome: 'success',
                redirectUrl: '/dashboard',
            });
        });

        it('clears stale field errors and applies success message without redirect', () => {
            const formState = createFormState();
            formState.submitting = true;
            formState.fieldErrors = {
                password: ['Too short'],
            };

            const normalizedResult = {
                ok: true,
                status: 200,
                message: 'Operation completed.',
                redirectUrl: null,
                data: {},
            };

            const result = applySuccessState(formState, normalizedResult);

            expect(result).toBe(formState);
            expect(formState.submitting).toBe(false);
            expect(formState.submitted).toBe(true);
            expect(formState.fieldErrors).toEqual({});
            expect(formState.message).toBe('Operation completed.');
            expect(formState.meta).toEqual({
                status: 200,
                outcome: 'success',
                redirectUrl: null,
            });
        });
    });

    describe('handleSuccess', () => {
        it('normalizes the response, updates state, and emits through context.emit', () => {
            const form = document.createElement('form');
            const formState = createFormState(form);
            formState.submitting = true;
            formState.fieldErrors = {
                email: ['Old error'],
            };

            const emit = vi.fn();
            const context = { emit };

            const responseResult = {
                status: 200,
                data: {
                    message: 'Login successful.',
                    redirect: {
                        url: '/dashboard',
                    },
                },
            };

            const result = handleSuccess(context, form, formState, responseResult);

            expect(result).toEqual({
                ok: true,
                status: 200,
                message: 'Login successful.',
                redirectUrl: '/dashboard',
                data: {
                    message: 'Login successful.',
                    redirect: {
                        url: '/dashboard',
                    },
                },
            });

            expect(formState.submitting).toBe(false);
            expect(formState.submitted).toBe(true);
            expect(formState.fieldErrors).toEqual({});
            expect(formState.message).toBe('Login successful.');
            expect(formState.meta).toEqual({
                status: 200,
                outcome: 'success',
                redirectUrl: '/dashboard',
            });

            expect(emit).toHaveBeenCalledTimes(1);
            expect(emit).toHaveBeenCalledWith('form_success', {
                form,
                status: 200,
                message: 'Login successful.',
                redirectUrl: '/dashboard',
                result: {
                    message: 'Login successful.',
                    redirect: {
                        url: '/dashboard',
                    },
                },
            });

            expect(dispatchEvent).not.toHaveBeenCalled();
        });

        it('falls back to browser dispatchEvent when context.emit is unavailable', () => {
            const form = document.createElement('form');
            const formState = createFormState(form);

            const responseResult = {
                status: 201,
                data: {
                    message: 'Created successfully.',
                },
            };

            const result = handleSuccess({}, form, formState, responseResult);

            expect(result).toEqual({
                ok: true,
                status: 201,
                message: 'Created successfully.',
                redirectUrl: null,
                data: {
                    message: 'Created successfully.',
                },
            });

            expect(dispatchEvent).toHaveBeenCalledTimes(1);
            expect(dispatchEvent).toHaveBeenCalledWith('form_success', {
                form,
                status: 201,
                message: 'Created successfully.',
                redirectUrl: null,
                result: {
                    message: 'Created successfully.',
                },
            });
        });

        it('uses default fallback message when response has no message', () => {
            const form = document.createElement('form');
            const formState = createFormState(form);

            const result = handleSuccess(null, form, formState, {
                status: 204,
                data: {},
            });

            expect(result).toEqual({
                ok: true,
                status: 204,
                message: 'Operation completed.',
                redirectUrl: null,
                data: {},
            });

            expect(formState.message).toBe('Operation completed.');
            expect(formState.fieldErrors).toEqual({});
            expect(formState.meta).toEqual({
                status: 204,
                outcome: 'success',
                redirectUrl: null,
            });
        });

        it('preserves redirect metadata on success state', () => {
            const form = document.createElement('form');
            const formState = createFormState(form);

            const result = handleSuccess(null, form, formState, {
                status: 200,
                data: {
                    redirect: {
                        url: '/welcome',
                    },
                },
            });

            expect(result.redirectUrl).toBe('/welcome');
            expect(formState.meta).toEqual({
                status: 200,
                outcome: 'success',
                redirectUrl: '/welcome',
            });
        });
    });
});