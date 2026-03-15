/**
 * AuthKit
 * -----------------------------------------------------------------------------
 * File: tests/js/modules/forms/submit.test.js
 * Author: Michael Erastus
 * Package: AuthKit
 *
 * Description:
 * -----------------------------------------------------------------------------
 * Unit tests for AuthKit AJAX form submission orchestration utilities.
 *
 * Responsibilities:
 * - Verify submit serialization resolution.
 * - Verify request descriptor building.
 * - Verify before-submit event payload creation and dispatch.
 * - Verify submit state preparation.
 * - Verify transport error normalization.
 * - Verify successful, failed, and thrown submission flows.
 * - Verify redirect, loading-state, and DOM feedback rendering integration.
 */

import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('../../../../resources/js/authkit/core/http.js', () => ({
    request: vi.fn(),
}));

vi.mock('../../../../resources/js/authkit/core/events.js', () => ({
    dispatchEvent: vi.fn(),
}));

vi.mock('../../../../resources/js/authkit/modules/forms/handle-success.js', () => ({
    handleSuccess: vi.fn(),
}));

vi.mock('../../../../resources/js/authkit/modules/forms/handle-error.js', () => ({
    handleError: vi.fn(),
}));

vi.mock('../../../../resources/js/authkit/modules/forms/serialize.js', async () => {
    const actual = await vi.importActual(
        '../../../../resources/js/authkit/modules/forms/serialize.js'
    );

    return {
        ...actual,
        buildSerializedForm: vi.fn(actual.buildSerializedForm),
    };
});

vi.mock('../../../../resources/js/authkit/modules/forms/redirect.js', () => ({
    handleRedirect: vi.fn(),
}));

vi.mock('../../../../resources/js/authkit/modules/forms/render-feedback.js', () => ({
    clearRenderedFeedback: vi.fn(),
    renderFormFeedback: vi.fn(),
}));

vi.mock('../../../../resources/js/authkit/modules/forms/loading.js', () => ({
    applyLoadingState: vi.fn(),
    clearLoadingState: vi.fn(),
}));

import {
    clearConfigCache,
    setConfig,
} from '../../../../resources/js/authkit/core/config.js';
import { request } from '../../../../resources/js/authkit/core/http.js';
import { dispatchEvent } from '../../../../resources/js/authkit/core/events.js';
import { handleSuccess } from '../../../../resources/js/authkit/modules/forms/handle-success.js';
import { handleError } from '../../../../resources/js/authkit/modules/forms/handle-error.js';
import { handleRedirect } from '../../../../resources/js/authkit/modules/forms/redirect.js';
import {
    clearRenderedFeedback,
    renderFormFeedback,
} from '../../../../resources/js/authkit/modules/forms/render-feedback.js';
import {
    applyLoadingState,
    clearLoadingState,
} from '../../../../resources/js/authkit/modules/forms/loading.js';

import {
    beginSubmitState,
    buildSubmitRequest,
    createBeforeSubmitDetail,
    createTransportErrorResult,
    emitBeforeSubmit,
    getSubmitSerialization,
    resolveSubmitBody,
    resolveSubmitHeaders,
    resolveSubmitMethod,
    resolveSubmitTransport,
    resolveSubmitUrl,
    shouldSubmitAsJson,
    submitForm,
} from '../../../../resources/js/authkit/modules/forms/submit.js';

import {
    buildSerializedForm,
} from '../../../../resources/js/authkit/modules/forms/serialize.js';

import { createFormState } from '../../../../resources/js/authkit/modules/forms/state.js';


describe('modules/forms/submit', () => {
    beforeEach(async () => {
        document.body.innerHTML = '';
        vi.resetAllMocks();
        clearConfigCache();

        window.AuthKit = {
            config: {},
        };

        const actualSerialize = await vi.importActual(
            '../../../../resources/js/authkit/modules/forms/serialize.js'
        );

        buildSerializedForm.mockImplementation(actualSerialize.buildSerializedForm);
    });

    function makeForm() {
        const form = document.createElement('form');
        form.method = 'post';
        form.action = 'https://example.com/login';

        const email = document.createElement('input');
        email.name = 'email';
        email.value = 'michael@example.com';

        const password = document.createElement('input');
        password.name = 'password';
        password.value = 'secret';

        form.append(email, password);

        return form;
    }

    describe('shouldSubmitAsJson', () => {
        it('prefers explicit options.asJson when true', () => {
            setConfig({
                forms: {
                    ajax: {
                        submitJson: false,
                    },
                },
            });

            expect(shouldSubmitAsJson({ asJson: true })).toBe(true);
        });

        it('prefers explicit options.asJson when false', () => {
            setConfig({
                forms: {
                    ajax: {
                        submitJson: true,
                    },
                },
            });

            expect(shouldSubmitAsJson({ asJson: false })).toBe(false);
        });

        it('falls back to runtime config when options.asJson is not provided', () => {
            setConfig({
                forms: {
                    ajax: {
                        submitJson: true,
                    },
                },
            });

            expect(shouldSubmitAsJson({})).toBe(true);
            expect(shouldSubmitAsJson(null)).toBe(true);
        });

        it('falls back to false when neither options nor config enable json submission', () => {
            setConfig({
                forms: {
                    ajax: {
                        submitJson: false,
                    },
                },
            });

            expect(shouldSubmitAsJson({})).toBe(false);
            expect(shouldSubmitAsJson(null)).toBe(false);
        });
    });

    describe('getSubmitSerialization', () => {
        it('returns normalized serialization settings from explicit options', () => {
            expect(getSubmitSerialization({ asJson: true })).toEqual({
                asJson: true,
            });

            expect(getSubmitSerialization({ asJson: false })).toEqual({
                asJson: false,
            });
        });

        it('returns normalized serialization settings from runtime config fallback', () => {
            setConfig({
                forms: {
                    ajax: {
                        submitJson: true,
                    },
                },
            });

            expect(getSubmitSerialization({})).toEqual({
                asJson: true,
            });
        });
    });

    describe('resolveSubmitUrl', () => {
        it('prefers explicit options.url when present', () => {
            const serializedForm = {
                action: 'https://example.com/default',
            };

            expect(resolveSubmitUrl(serializedForm, {
                url: 'https://example.com/override',
            })).toBe('https://example.com/override');
        });

        it('falls back to serialized form action', () => {
            const serializedForm = {
                action: 'https://example.com/default',
            };

            expect(resolveSubmitUrl(serializedForm, {}))
                .toBe('https://example.com/default');
        });

        it('returns empty string when no usable url exists', () => {
            expect(resolveSubmitUrl({}, {})).toBe('');
        });
    });

    describe('resolveSubmitMethod', () => {
        it('prefers explicit options.method and uppercases it', () => {
            const serializedForm = {
                method: 'POST',
            };

            expect(resolveSubmitMethod(serializedForm, {
                method: 'patch',
            })).toBe('PATCH');
        });

        it('falls back to serialized form method', () => {
            const serializedForm = {
                method: 'post',
            };

            expect(resolveSubmitMethod(serializedForm, {})).toBe('POST');
        });

        it('falls back to POST when no usable method exists', () => {
            expect(resolveSubmitMethod({}, {})).toBe('POST');
        });
    });

    describe('resolveSubmitHeaders', () => {
        it('returns a shallow clone of valid headers', () => {
            const headers = {
                Accept: 'application/json',
                'X-Test': '1',
            };

            const result = resolveSubmitHeaders({ headers });

            expect(result).toEqual(headers);
            expect(result).not.toBe(headers);
        });

        it('returns empty object for invalid headers', () => {
            expect(resolveSubmitHeaders({ headers: null })).toEqual({});
            expect(resolveSubmitHeaders({ headers: [] })).toEqual({});
            expect(resolveSubmitHeaders({})).toEqual({});
        });
    });

    describe('resolveSubmitTransport', () => {
        it('returns normalized transport options with defaults', () => {
            const result = resolveSubmitTransport({});

            expect(result).toEqual({
                credentials: 'same-origin',
            });
        });

        it('passes through supported transport options', () => {
            const controller = new AbortController();

            const result = resolveSubmitTransport({
                credentials: 'include',
                signal: controller.signal,
                mode: 'cors',
                redirect: 'manual',
            });

            expect(result).toEqual({
                credentials: 'include',
                signal: controller.signal,
                mode: 'cors',
                redirect: 'manual',
            });
        });

        it('omits nullish optional transport values', () => {
            const result = resolveSubmitTransport({
                credentials: 'same-origin',
                signal: null,
                mode: null,
                redirect: null,
            });

            expect(result).toEqual({
                credentials: 'same-origin',
            });
            expect(result).not.toHaveProperty('signal');
            expect(result).not.toHaveProperty('mode');
            expect(result).not.toHaveProperty('redirect');
        });
    });

    describe('resolveSubmitBody', () => {
        it('returns serialized object data in json mode', () => {
            const serializedForm = {
                data: {
                    email: 'michael@example.com',
                },
                formData: new FormData(),
            };

            expect(resolveSubmitBody(serializedForm, { asJson: true })).toEqual({
                email: 'michael@example.com',
            });
        });

        it('returns FormData in default mode', () => {
            const formData = new FormData();
            formData.append('email', 'michael@example.com');

            const serializedForm = {
                data: {
                    email: 'michael@example.com',
                },
                formData,
            };

            expect(resolveSubmitBody(serializedForm, { asJson: false })).toBe(formData);
        });
    });

    describe('buildSubmitRequest', () => {
        it('builds a normalized request descriptor for form-data submission', () => {
            const form = makeForm();

            const result = buildSubmitRequest(form);

            expect(result.form).toBe(form);
            expect(result.serializedForm).toEqual(buildSerializedForm(form));
            expect(result.url).toBe('https://example.com/login');
            expect(result.method).toBe('POST');
            expect(result.body).toBeInstanceOf(FormData);
            expect(result.asJson).toBe(false);
            expect(result.headers).toEqual({});
            expect(result.credentials).toBe('same-origin');
            expect(result).not.toHaveProperty('mode');
            expect(result).not.toHaveProperty('redirect');
            expect(result).not.toHaveProperty('signal');
        });

        it('builds a normalized request descriptor for json submission', () => {
            const form = makeForm();

            const result = buildSubmitRequest(form, {
                asJson: true,
                url: 'https://example.com/custom',
                method: 'patch',
                headers: {
                    'X-Test': '1',
                },
                credentials: 'include',
                mode: 'cors',
                redirect: 'manual',
            });

            expect(result.form).toBe(form);
            expect(result.url).toBe('https://example.com/custom');
            expect(result.method).toBe('PATCH');
            expect(result.body).toEqual({
                email: 'michael@example.com',
                password: 'secret',
            });
            expect(result.asJson).toBe(true);
            expect(result.headers).toEqual({
                'X-Test': '1',
            });
            expect(result.credentials).toBe('include');
            expect(result.mode).toBe('cors');
            expect(result.redirect).toBe('manual');
        });

        it('does not include null transport values in request descriptor', () => {
            const form = makeForm();

            const result = buildSubmitRequest(form, {
                mode: null,
                redirect: null,
                signal: null,
            });

            expect(result.credentials).toBe('same-origin');
            expect(result).not.toHaveProperty('mode');
            expect(result).not.toHaveProperty('redirect');
            expect(result).not.toHaveProperty('signal');
        });
    });

    describe('createBeforeSubmitDetail', () => {
        it('builds the before-submit event detail payload', () => {
            const form = makeForm();
            const submitRequest = buildSubmitRequest(form, { asJson: true });

            expect(createBeforeSubmitDetail(form, submitRequest)).toEqual({
                form,
                url: 'https://example.com/login',
                method: 'POST',
                asJson: true,
                data: {
                    email: 'michael@example.com',
                    password: 'secret',
                },
            });
        });
    });

    describe('emitBeforeSubmit', () => {
        it('uses context.emit when available', () => {
            const form = makeForm();
            const submitRequest = buildSubmitRequest(form);
            const emit = vi.fn();

            const context = { emit };

            emitBeforeSubmit(context, form, submitRequest);

            expect(emit).toHaveBeenCalledTimes(1);
            expect(emit).toHaveBeenCalledWith('form_before_submit', {
                form,
                url: 'https://example.com/login',
                method: 'POST',
                asJson: false,
                data: {
                    email: 'michael@example.com',
                    password: 'secret',
                },
            });

            expect(dispatchEvent).not.toHaveBeenCalled();
        });

        it('falls back to dispatchEvent when context.emit is unavailable', () => {
            const form = makeForm();
            const submitRequest = buildSubmitRequest(form);

            emitBeforeSubmit({}, form, submitRequest);

            expect(dispatchEvent).toHaveBeenCalledTimes(1);
            expect(dispatchEvent).toHaveBeenCalledWith('form_before_submit', {
                form,
                url: 'https://example.com/login',
                method: 'POST',
                asJson: false,
                data: {
                    email: 'michael@example.com',
                    password: 'secret',
                },
            });
        });
    });

    describe('beginSubmitState', () => {
        it('prepares form state for a new submission attempt', () => {
            const form = makeForm();
            const formState = createFormState();
            formState.fieldErrors = {
                email: ['Required'],
            };
            formState.message = 'Old message';
            formState.meta = {
                previous: true,
            };

            const submitRequest = {
                form,
                url: 'https://example.com/login',
                method: 'POST',
                asJson: true,
            };

            const result = beginSubmitState(formState, submitRequest);

            expect(result).toBe(formState);
            expect(formState.fieldErrors).toEqual({});
            expect(formState.message).toBeNull();
            expect(formState.submitting).toBe(true);
            expect(formState.submitted).toBe(false);
            expect(formState.meta).toEqual({
                previous: true,
                requestUrl: 'https://example.com/login',
                requestMethod: 'POST',
                asJson: true,
                outcome: null,
                loading: true,
            });
            expect(clearRenderedFeedback).toHaveBeenCalledTimes(1);
            expect(clearRenderedFeedback).toHaveBeenCalledWith(form);
        });
    });

    describe('createTransportErrorResult', () => {
        it('builds a normalized transport failure result from an error', () => {
            const error = new Error('Network failed.');

            expect(createTransportErrorResult(error)).toEqual({
                status: 0,
                data: {
                    ok: false,
                    status: 0,
                    message: 'Network failed.',
                    errors: [],
                },
            });
        });

        it('uses fallback message when error message is invalid', () => {
            expect(createTransportErrorResult({})).toEqual({
                status: 0,
                data: {
                    ok: false,
                    status: 0,
                    message: 'Unable to submit the form. Please try again.',
                    errors: [],
                },
            });
        });
    });

    describe('submitForm', () => {
        it('returns normalized error result immediately when submit url is empty', async () => {
            const form = document.createElement('form');
            const formState = createFormState(form);

            buildSerializedForm.mockReturnValue({
                form,
                action: '',
                method: 'POST',
                formData: new FormData(),
                data: {},
            });

            handleError.mockReturnValue({
                ok: false,
                status: 0,
                message: 'Form submission requires a valid action URL.',
            });

            const result = await submitForm(null, form, formState);

            expect(buildSerializedForm).toHaveBeenCalledTimes(1);
            expect(buildSerializedForm).toHaveBeenCalledWith(form);

            expect(handleError).toHaveBeenCalledTimes(1);
            expect(handleError).toHaveBeenCalledWith(
                null,
                form,
                formState,
                {
                    status: 0,
                    data: {
                        ok: false,
                        status: 0,
                        message: 'Form submission requires a valid action URL.',
                    },
                }
            );

            expect(result).toEqual({
                ok: false,
                status: 0,
                message: 'Form submission requires a valid action URL.',
            });

            expect(request).not.toHaveBeenCalled();
            expect(handleSuccess).not.toHaveBeenCalled();
            expect(dispatchEvent).not.toHaveBeenCalled();
            expect(handleRedirect).not.toHaveBeenCalled();
            expect(renderFormFeedback).not.toHaveBeenCalled();
            expect(clearRenderedFeedback).not.toHaveBeenCalled();
            expect(applyLoadingState).not.toHaveBeenCalled();
            expect(clearLoadingState).not.toHaveBeenCalled();
        });

        it('submits successfully and routes through handleSuccess', async () => {
            const form = makeForm();
            const formState = createFormState(form);
            const context = { emit: vi.fn() };

            const responseResult = {
                ok: true,
                status: 200,
                data: {
                    message: 'Login successful.',
                },
            };

            const normalizedSuccess = {
                ok: true,
                status: 200,
                message: 'Login successful.',
                redirectUrl: null,
            };

            request.mockResolvedValue(responseResult);
            handleSuccess.mockReturnValue(normalizedSuccess);

            const afterSubmit = vi.fn();

            const result = await submitForm(context, form, formState, {
                afterSubmit,
            });

            expect(clearRenderedFeedback).toHaveBeenCalledTimes(1);
            expect(clearRenderedFeedback).toHaveBeenCalledWith(form);

            expect(applyLoadingState).toHaveBeenCalledTimes(1);
            expect(applyLoadingState).toHaveBeenCalledWith(form, context, {
                afterSubmit,
            });

            expect(clearLoadingState).toHaveBeenCalledTimes(1);
            expect(clearLoadingState).toHaveBeenCalledWith(form, context, {
                afterSubmit,
            });

            expect(renderFormFeedback).toHaveBeenCalledTimes(1);
            expect(renderFormFeedback).toHaveBeenCalledWith(
                form,
                formState,
                'success'
            );

            expect(request).toHaveBeenCalledTimes(1);
            expect(request).toHaveBeenCalledWith(
                'https://example.com/login',
                expect.objectContaining({
                    method: 'POST',
                    body: expect.any(FormData),
                    asJson: false,
                    headers: {},
                    credentials: 'same-origin',
                })
            );

            const requestOptions = request.mock.calls[0][1];
            expect(requestOptions).not.toHaveProperty('mode');
            expect(requestOptions).not.toHaveProperty('redirect');
            expect(requestOptions).not.toHaveProperty('signal');

            expect(handleSuccess).toHaveBeenCalledTimes(1);
            expect(handleSuccess).toHaveBeenCalledWith(
                context,
                form,
                formState,
                responseResult
            );

            expect(handleRedirect).toHaveBeenCalledTimes(1);
            expect(handleRedirect).toHaveBeenCalledWith(
                context,
                normalizedSuccess
            );

            expect(handleError).not.toHaveBeenCalled();

            expect(afterSubmit).toHaveBeenCalledTimes(1);
            expect(afterSubmit).toHaveBeenCalledWith(
                normalizedSuccess,
                form,
                formState,
                context
            );

            expect(result).toBe(normalizedSuccess);
        });

        it('submits unsuccessfully and routes through handleError', async () => {
            const form = makeForm();
            const formState = createFormState(form);
            const context = { emit: vi.fn() };

            const responseResult = {
                ok: false,
                status: 422,
                data: {
                    message: 'Validation failed.',
                },
            };

            const normalizedError = {
                ok: false,
                status: 422,
                message: 'Validation failed.',
            };

            request.mockResolvedValue(responseResult);
            handleError.mockReturnValue(normalizedError);

            const afterSubmit = vi.fn();

            const result = await submitForm(context, form, formState, {
                afterSubmit,
            });

            expect(clearRenderedFeedback).toHaveBeenCalledTimes(1);
            expect(clearRenderedFeedback).toHaveBeenCalledWith(form);

            expect(applyLoadingState).toHaveBeenCalledTimes(1);
            expect(clearLoadingState).toHaveBeenCalledTimes(1);

            expect(renderFormFeedback).toHaveBeenCalledTimes(1);
            expect(renderFormFeedback).toHaveBeenCalledWith(
                form,
                formState,
                'error'
            );

            expect(request).toHaveBeenCalledTimes(1);

            expect(handleError).toHaveBeenCalledTimes(1);
            expect(handleError).toHaveBeenCalledWith(
                context,
                form,
                formState,
                responseResult
            );

            expect(handleSuccess).not.toHaveBeenCalled();
            expect(handleRedirect).not.toHaveBeenCalled();

            expect(afterSubmit).toHaveBeenCalledTimes(1);
            expect(afterSubmit).toHaveBeenCalledWith(
                normalizedError,
                form,
                formState,
                context
            );

            expect(result).toBe(normalizedError);
        });

        it('converts thrown transport failures into normalized error handling flow', async () => {
            const form = makeForm();
            const formState = createFormState(form);
            const context = { emit: vi.fn() };

            const thrownError = new Error('Network failed.');
            const normalizedError = {
                ok: false,
                status: 0,
                message: 'Network failed.',
            };

            request.mockRejectedValue(thrownError);
            handleError.mockReturnValue(normalizedError);

            const afterSubmit = vi.fn();

            const result = await submitForm(context, form, formState, {
                afterSubmit,
            });

            expect(clearRenderedFeedback).toHaveBeenCalledTimes(1);
            expect(clearRenderedFeedback).toHaveBeenCalledWith(form);

            expect(applyLoadingState).toHaveBeenCalledTimes(1);
            expect(clearLoadingState).toHaveBeenCalledTimes(1);

            expect(renderFormFeedback).toHaveBeenCalledTimes(1);
            expect(renderFormFeedback).toHaveBeenCalledWith(
                form,
                formState,
                'error'
            );

            expect(request).toHaveBeenCalledTimes(1);

            expect(handleError).toHaveBeenCalledTimes(1);
            expect(handleError).toHaveBeenCalledWith(
                context,
                form,
                formState,
                {
                    status: 0,
                    data: {
                        ok: false,
                        status: 0,
                        message: 'Network failed.',
                        errors: [],
                    },
                }
            );

            expect(handleSuccess).not.toHaveBeenCalled();
            expect(handleRedirect).not.toHaveBeenCalled();

            expect(afterSubmit).toHaveBeenCalledTimes(1);
            expect(afterSubmit).toHaveBeenCalledWith(
                normalizedError,
                form,
                formState,
                context
            );

            expect(result).toBe(normalizedError);
        });

        it('supports json submission mode', async () => {
            const form = makeForm();
            const formState = createFormState(form);

            request.mockResolvedValue({
                ok: true,
                status: 200,
                data: {},
            });

            handleSuccess.mockReturnValue({
                ok: true,
                status: 200,
                message: 'Done',
            });

            await submitForm(null, form, formState, {
                asJson: true,
            });

            expect(clearRenderedFeedback).toHaveBeenCalledTimes(1);
            expect(clearRenderedFeedback).toHaveBeenCalledWith(form);

            expect(applyLoadingState).toHaveBeenCalledTimes(1);
            expect(clearLoadingState).toHaveBeenCalledTimes(1);

            expect(renderFormFeedback).toHaveBeenCalledTimes(1);
            expect(renderFormFeedback).toHaveBeenCalledWith(
                form,
                formState,
                'success'
            );

            expect(request).toHaveBeenCalledWith(
                'https://example.com/login',
                expect.objectContaining({
                    method: 'POST',
                    body: {
                        email: 'michael@example.com',
                        password: 'secret',
                    },
                    asJson: true,
                })
            );
        });

        it('allows beforeSubmit to override parts of the submit request', async () => {
            const form = makeForm();
            const formState = createFormState(form);

            request.mockResolvedValue({
                ok: true,
                status: 200,
                data: {},
            });

            handleSuccess.mockReturnValue({
                ok: true,
                status: 200,
                message: 'Done',
            });

            const beforeSubmit = vi.fn(async (receivedForm, submitRequest, receivedState, context) => {
                expect(receivedForm).toBe(form);
                expect(submitRequest.url).toBe('https://example.com/login');
                expect(receivedState).toBe(formState);
                expect(context).toEqual({});

                return {
                    url: 'https://example.com/override',
                    method: 'PATCH',
                    headers: {
                        'X-Test': '1',
                    },
                };
            });

            await submitForm({}, form, formState, {
                beforeSubmit,
            });

            expect(beforeSubmit).toHaveBeenCalledTimes(1);
            expect(clearRenderedFeedback).toHaveBeenCalledTimes(1);
            expect(clearRenderedFeedback).toHaveBeenCalledWith(form);
            expect(applyLoadingState).toHaveBeenCalledTimes(1);
            expect(clearLoadingState).toHaveBeenCalledTimes(1);

            expect(renderFormFeedback).toHaveBeenCalledTimes(1);
            expect(renderFormFeedback).toHaveBeenCalledWith(
                form,
                formState,
                'success'
            );

            expect(request).toHaveBeenCalledWith(
                'https://example.com/override',
                expect.objectContaining({
                    method: 'PATCH',
                    headers: {
                        'X-Test': '1',
                    },
                })
            );
        });

        it('allows beforeSubmit to clear null transport values safely', async () => {
            const form = makeForm();
            const formState = createFormState(form);

            request.mockResolvedValue({
                ok: true,
                status: 200,
                data: {},
            });

            handleSuccess.mockReturnValue({
                ok: true,
                status: 200,
                message: 'Done',
            });

            await submitForm({}, form, formState, {
                beforeSubmit: async () => ({
                    mode: null,
                    redirect: null,
                    signal: null,
                }),
            });

            expect(clearRenderedFeedback).toHaveBeenCalledTimes(1);
            expect(clearRenderedFeedback).toHaveBeenCalledWith(form);
            expect(applyLoadingState).toHaveBeenCalledTimes(1);
            expect(clearLoadingState).toHaveBeenCalledTimes(1);

            expect(renderFormFeedback).toHaveBeenCalledTimes(1);
            expect(renderFormFeedback).toHaveBeenCalledWith(
                form,
                formState,
                'success'
            );

            expect(request).toHaveBeenCalledTimes(1);

            const requestOptions = request.mock.calls[0][1];
            expect(requestOptions).not.toHaveProperty('mode');
            expect(requestOptions).not.toHaveProperty('redirect');
            expect(requestOptions).not.toHaveProperty('signal');
        });

        it('ignores invalid beforeSubmit return values', async () => {
            const form = makeForm();
            const formState = createFormState(form);

            request.mockResolvedValue({
                ok: true,
                status: 200,
                data: {},
            });

            handleSuccess.mockReturnValue({
                ok: true,
                status: 200,
                message: 'Done',
            });

            const beforeSubmit = vi.fn(async () => 'invalid');

            await submitForm(null, form, formState, {
                beforeSubmit,
            });

            expect(clearRenderedFeedback).toHaveBeenCalledTimes(1);
            expect(clearRenderedFeedback).toHaveBeenCalledWith(form);
            expect(applyLoadingState).toHaveBeenCalledTimes(1);
            expect(clearLoadingState).toHaveBeenCalledTimes(1);

            expect(renderFormFeedback).toHaveBeenCalledTimes(1);
            expect(renderFormFeedback).toHaveBeenCalledWith(
                form,
                formState,
                'success'
            );

            expect(request).toHaveBeenCalledWith(
                'https://example.com/login',
                expect.objectContaining({
                    method: 'POST',
                })
            );
        });

        it('does not call afterSubmit when it is not a function', async () => {
            const form = makeForm();
            const formState = createFormState(form);

            request.mockResolvedValue({
                ok: true,
                status: 200,
                data: {},
            });

            handleSuccess.mockReturnValue({
                ok: true,
                status: 200,
                message: 'Done',
            });

            const result = await submitForm(null, form, formState, {
                afterSubmit: null,
            });

            expect(clearRenderedFeedback).toHaveBeenCalledTimes(1);
            expect(clearRenderedFeedback).toHaveBeenCalledWith(form);
            expect(applyLoadingState).toHaveBeenCalledTimes(1);
            expect(clearLoadingState).toHaveBeenCalledTimes(1);

            expect(renderFormFeedback).toHaveBeenCalledTimes(1);
            expect(renderFormFeedback).toHaveBeenCalledWith(
                form,
                formState,
                'success'
            );

            expect(result).toEqual({
                ok: true,
                status: 200,
                message: 'Done',
            });
        });

        it('emits before-submit event before performing request', async () => {
            const form = makeForm();
            const formState = createFormState(form);
            const emit = vi.fn();
            const context = { emit };

            request.mockResolvedValue({
                ok: true,
                status: 200,
                data: {},
            });

            handleSuccess.mockReturnValue({
                ok: true,
                status: 200,
                message: 'Done',
            });

            await submitForm(context, form, formState);

            expect(emit).toHaveBeenCalledWith('form_before_submit', {
                form,
                url: 'https://example.com/login',
                method: 'POST',
                asJson: false,
                data: {
                    email: 'michael@example.com',
                    password: 'secret',
                },
            });

            expect(clearRenderedFeedback).toHaveBeenCalledTimes(1);
            expect(clearRenderedFeedback).toHaveBeenCalledWith(form);
            expect(applyLoadingState).toHaveBeenCalledTimes(1);
            expect(clearLoadingState).toHaveBeenCalledTimes(1);

            expect(renderFormFeedback).toHaveBeenCalledTimes(1);
            expect(renderFormFeedback).toHaveBeenCalledWith(
                form,
                formState,
                'success'
            );

            expect(request).toHaveBeenCalledTimes(1);
        });

        it('prepares form state before performing request', async () => {
            const form = makeForm();
            const formState = createFormState(form);
            formState.fieldErrors = { email: ['Old error'] };
            formState.message = 'Old message';

            request.mockResolvedValue({
                ok: true,
                status: 200,
                data: {},
            });

            handleSuccess.mockReturnValue({
                ok: true,
                status: 200,
                message: 'Done',
            });

            await submitForm(null, form, formState, {
                asJson: true,
            });

            expect(clearRenderedFeedback).toHaveBeenCalledTimes(1);
            expect(clearRenderedFeedback).toHaveBeenCalledWith(form);
            expect(applyLoadingState).toHaveBeenCalledTimes(1);
            expect(clearLoadingState).toHaveBeenCalledTimes(1);

            expect(renderFormFeedback).toHaveBeenCalledTimes(1);
            expect(renderFormFeedback).toHaveBeenCalledWith(
                form,
                formState,
                'success'
            );

            expect(formState.submitting).toBe(true);
            expect(formState.submitted).toBe(false);
            expect(formState.fieldErrors).toEqual({});
            expect(formState.message).toBeNull();
            expect(formState.meta).toEqual(
                expect.objectContaining({
                    requestUrl: 'https://example.com/login',
                    requestMethod: 'POST',
                    asJson: true,
                    outcome: null,
                    loading: false,
                })
            );
        });
    });
});