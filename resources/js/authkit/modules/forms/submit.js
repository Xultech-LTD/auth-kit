/**
 * AuthKit
 * -----------------------------------------------------------------------------
 * File: js/modules/forms/submit.js
 * Author: Michael Erastus
 * Package: AuthKit
 *
 * Description:
 * -----------------------------------------------------------------------------
 * Form submission orchestration utilities for the AuthKit AJAX forms module.
 *
 * This file is responsible for executing a normalized AuthKit form submission
 * lifecycle using the shared state, serializer, HTTP layer, and result
 * handlers.
 *
 * Responsibilities:
 * - Resolve the submission target URL and HTTP method.
 * - Serialize the form payload according to the active submission mode.
 * - Dispatch the pre-submit AuthKit browser event.
 * - Mark the form state as actively submitting.
 * - Execute the HTTP request through the shared core HTTP utility.
 * - Route the normalized HTTP result into success or error handlers.
 * - Convert thrown transport failures into normalized error results.
 *
 * Design notes:
 * - This file does not bind DOM event listeners directly.
 * - This file does not render feedback into the DOM directly.
 * - This file is intentionally orchestration-focused and delegates:
 *   - payload creation to serialize.js
 *   - state mutation to state.js
 *   - result normalization to handle-success.js and handle-error.js
 * - Page modules may extend the submission lifecycle by supplying hooks or
 *   additional request options through the submit options object.
 *
 * Supported submission modes:
 * - form-data (default)
 * - json
 *
 * Expected return shape:
 * - Success and error outcomes are returned as normalized result objects from
 *   handle-success.js or handle-error.js.
 *
 * @package   AuthKit
 * @author    Michael Erastus
 * @license   MIT
 */

import { request } from '../../core/http.js';
import { dispatchEvent } from '../../core/events.js';
import { dataGet, isFunction, isObject, isString, normalizeString } from '../../core/helpers.js';
import { buildSerializedForm } from './serialize.js';
import { clearFieldErrors, clearMessage, setMeta, setSubmitting } from './state.js';
import { handleSuccess } from './handle-success.js';
import { handleError } from './handle-error.js';


/**
 * Resolve whether the current submission should be sent as JSON.
 *
 * Resolution order:
 * - options.asJson
 * - false fallback
 *
 * @param {Object} [options={}]
 * @returns {boolean}
 */
export function shouldSubmitAsJson(options = {}) {
    return dataGet(options, 'asJson', false) === true;
}


/**
 * Resolve normalized serialization settings for a submission attempt.
 *
 * Returned shape:
 * - asJson: boolean
 *
 * @param {Object} [options={}]
 * @returns {{ asJson: boolean }}
 */
export function getSubmitSerialization(options = {}) {
    return {
        asJson: shouldSubmitAsJson(options),
    };
}


/**
 * Resolve the effective submission URL.
 *
 * Resolution order:
 * - explicit options.url
 * - serialized form action
 *
 * @param {Object} serializedForm
 * @param {Object} [options={}]
 * @returns {string}
 */
export function resolveSubmitUrl(serializedForm, options = {}) {
    const explicitUrl = normalizeString(dataGet(options, 'url', ''), '');

    if (explicitUrl !== '') {
        return explicitUrl;
    }

    return normalizeString(dataGet(serializedForm, 'action', ''), '');
}


/**
 * Resolve the effective submission method.
 *
 * Resolution order:
 * - explicit options.method
 * - serialized form method
 * - POST fallback
 *
 * @param {Object} serializedForm
 * @param {Object} [options={}]
 * @returns {string}
 */
export function resolveSubmitMethod(serializedForm, options = {}) {
    const explicitMethod = normalizeString(dataGet(options, 'method', ''), '');

    if (explicitMethod !== '') {
        return explicitMethod.toUpperCase();
    }

    const serializedMethod = normalizeString(dataGet(serializedForm, 'method', 'POST'), 'POST');

    return serializedMethod.toUpperCase();
}


/**
 * Resolve additional request headers for the submission.
 *
 * @param {Object} [options={}]
 * @returns {Record<string, string>}
 */
export function resolveSubmitHeaders(options = {}) {
    const headers = dataGet(options, 'headers', {});

    return isObject(headers) ? { ...headers } : {};
}


/**
 * Resolve additional request transport options.
 *
 * Supported passthrough keys:
 * - credentials
 * - signal
 * - mode
 * - redirect
 *
 * Notes:
 * - Nullish optional transport values are omitted instead of being forwarded.
 * - This avoids invalid fetch RequestInit enum values such as mode: null.
 *
 * @param {Object} [options={}]
 * @returns {Object}
 */
export function resolveSubmitTransport(options = {}) {
    const transport = {
        credentials: dataGet(options, 'credentials', 'same-origin'),
    };

    const signal = dataGet(options, 'signal', undefined);
    const mode = dataGet(options, 'mode', undefined);
    const redirect = dataGet(options, 'redirect', undefined);

    if (signal !== undefined && signal !== null) {
        transport.signal = signal;
    }

    if (mode !== undefined && mode !== null) {
        transport.mode = mode;
    }

    if (redirect !== undefined && redirect !== null) {
        transport.redirect = redirect;
    }

    return transport;
}


/**
 * Resolve the submission body payload from a serialized form descriptor.
 *
 * Rules:
 * - JSON mode uses serialized object data
 * - default mode uses FormData
 *
 * @param {Object} serializedForm
 * @param {Object} serialization
 * @returns {*}
 */
export function resolveSubmitBody(serializedForm, serialization) {
    if (serialization.asJson === true) {
        return dataGet(serializedForm, 'data', {});
    }

    return dataGet(serializedForm, 'formData', null);
}


/**
 * Build a normalized request descriptor for a form submission.
 *
 * Returned shape:
 * - form
 * - serializedForm
 * - url
 * - method
 * - body
 * - asJson
 * - headers
 * - credentials
 * - signal
 * - mode
 * - redirect
 *
 * @param {HTMLFormElement} form
 * @param {Object} [options={}]
 * @returns {Object}
 */
export function buildSubmitRequest(form, options = {}) {
    const serializedForm = buildSerializedForm(form);
    const serialization = getSubmitSerialization(options);

    return {
        form: dataGet(serializedForm, 'form', null),
        serializedForm,
        url: resolveSubmitUrl(serializedForm, options),
        method: resolveSubmitMethod(serializedForm, options),
        body: resolveSubmitBody(serializedForm, serialization),
        asJson: serialization.asJson === true,
        headers: resolveSubmitHeaders(options),
        ...resolveSubmitTransport(options),
    };
}


/**
 * Build the detail payload for the AuthKit before-submit event.
 *
 * @param {HTMLFormElement} form
 * @param {Object} submitRequest
 * @returns {Object}
 */
export function createBeforeSubmitDetail(form, submitRequest) {
    return {
        form,
        url: submitRequest.url,
        method: submitRequest.method,
        asJson: submitRequest.asJson === true,
        data: dataGet(submitRequest, 'serializedForm.data', {}),
    };
}


/**
 * Dispatch the configured AuthKit before-submit event.
 *
 * @param {Object|null} context
 * @param {HTMLFormElement} form
 * @param {Object} submitRequest
 * @returns {CustomEvent|null}
 */
export function emitBeforeSubmit(context, form, submitRequest) {
    const detail = createBeforeSubmitDetail(form, submitRequest);

    if (context && isFunction(context.emit)) {
        return context.emit('form_before_submit', detail);
    }

    return dispatchEvent('form_before_submit', detail);
}


/**
 * Prepare the form state for a new submission attempt.
 *
 * State updates:
 * - clears previous field errors
 * - clears previous top-level message
 * - stores request diagnostics in meta
 * - marks the form as submitting
 *
 * @param {Object} formState
 * @param {Object} submitRequest
 * @returns {Object|null}
 */
export function beginSubmitState(formState, submitRequest) {
    clearFieldErrors(formState);
    clearMessage(formState);

    setMeta(formState, {
        requestUrl: submitRequest.url,
        requestMethod: submitRequest.method,
        asJson: submitRequest.asJson === true,
        outcome: null,
    });

    setSubmitting(formState, true);

    return formState;
}


/**
 * Build a normalized synthetic transport failure result.
 *
 * This mirrors the core HTTP normalized response shape closely enough for the
 * shared error handler to process it consistently.
 *
 * @param {*} error
 * @returns {{status: number, data: Object}}
 */
export function createTransportErrorResult(error) {
    const message = isString(error?.message) && error.message.trim() !== ''
        ? error.message
        : 'Unable to submit the form. Please try again.';

    return {
        status: 0,
        data: {
            ok: false,
            status: 0,
            message,
            errors: [],
        },
    };
}

/**
 * Submit an AuthKit form through the shared runtime HTTP flow.
 *
 * Responsibilities:
 * - resolve request details
 * - emit the before-submit event
 * - prepare state for submission
 * - execute the HTTP request
 * - route the result into success or error handling
 *
 * Optional hooks:
 * - options.beforeSubmit(form, submitRequest, formState, context)
 * - options.afterSubmit(result, form, formState, context)
 *
 * Hook behavior:
 * - beforeSubmit may return a partial request object to merge into the
 *   normalized submit request.
 * - afterSubmit receives the normalized result returned by the success or error
 *   handler.
 *
 * @param {Object|null} context
 * @param {HTMLFormElement} form
 * @param {Object} formState
 * @param {Object} [options={}]
 * @returns {Promise<Object>}
 */
export async function submitForm(context, form, formState, options = {}) {
    const submitRequest = buildSubmitRequest(form, options);

    if (submitRequest.url === '') {
        return handleError(context, form, formState, {
            status: 0,
            data: {
                ok: false,
                status: 0,
                message: 'Form submission requires a valid action URL.',
            },
        });
    }

    if (isFunction(options.beforeSubmit)) {
        const maybeNextRequest = await options.beforeSubmit(form, submitRequest, formState, context);

        if (isObject(maybeNextRequest)) {
            Object.assign(submitRequest, maybeNextRequest);
        }
    }

    emitBeforeSubmit(context, form, submitRequest);
    beginSubmitState(formState, submitRequest);

    const requestOptions = {
        method: submitRequest.method,
        body: submitRequest.body,
        asJson: submitRequest.asJson,
        headers: submitRequest.headers,
        credentials: submitRequest.credentials,
    };

    if (submitRequest.signal !== undefined && submitRequest.signal !== null) {
        requestOptions.signal = submitRequest.signal;
    }

    if (submitRequest.mode !== undefined && submitRequest.mode !== null) {
        requestOptions.mode = submitRequest.mode;
    }

    if (submitRequest.redirect !== undefined && submitRequest.redirect !== null) {
        requestOptions.redirect = submitRequest.redirect;
    }

    try {
        const responseResult = await request(submitRequest.url, requestOptions);

        const normalizedResult = responseResult?.ok
            ? handleSuccess(context, form, formState, responseResult)
            : handleError(context, form, formState, responseResult);

        if (isFunction(options.afterSubmit)) {
            await options.afterSubmit(normalizedResult, form, formState, context);
        }

        return normalizedResult;
    } catch (error) {
        const normalizedResult = handleError(
            context,
            form,
            formState,
            createTransportErrorResult(error)
        );

        if (isFunction(options.afterSubmit)) {
            await options.afterSubmit(normalizedResult, form, formState, context);
        }

        return normalizedResult;
    }
}