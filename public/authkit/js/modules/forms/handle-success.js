/**
 * AuthKit
 * -----------------------------------------------------------------------------
 * File: handle-success.js
 * Author: Michael Erastus
 * Package: AuthKit
 *
 * Description:
 * -----------------------------------------------------------------------------
 * Success response handling utilities for the AuthKit AJAX forms module.
 *
 * This file is responsible for normalizing successful form submission outcomes
 * into a predictable client-side structure that page modules and UI integrations
 * can consume consistently.
 *
 * Responsibilities:
 * - Normalize successful HTTP response payloads.
 * - Extract success messages.
 * - Extract redirect intent from standardized AuthKit responses.
 * - Clear stale field validation errors after success.
 * - Update the shared form state with normalized success data.
 * - Dispatch AuthKit form success events through the provided runtime context.
 *
 * Design notes:
 * - This file does not perform the actual browser redirect automatically.
 * - Page modules may choose whether to follow redirect intent immediately.
 * - This file does not render success messages into the DOM directly.
 * - The normalized output must remain stable across standard success responses.
 *
 * Expected backend payload compatibility:
 * - Standard AuthKitActionResult success responses.
 * - Generic JSON success payloads.
 * - Responses carrying redirect.url metadata.
 *
 * @package   AuthKit
 * @author    Michael Erastus
 * @license   MIT
 */

import { dispatchEvent } from '../../core/events.js';
import {dataGet, isFunction, isObject, isString} from '../../core/helpers.js';
import { clearFieldErrors, clearMessage, setLastResult, setMessage, setMeta, setSubmitting, } from './state.js';


/**
 * Resolve the best redirect URL from a successful response payload.
 *
 * Supported source:
 * - redirect.url
 *
 * @param {Object} data
 * @returns {string|null}
 */
export function resolveSuccessRedirectUrl(data = {}) {
    const redirectUrl = dataGet(data, 'redirect.url', null);

    if (isString(redirectUrl) && redirectUrl.trim() !== '') {
        return redirectUrl;
    }

    return null;
}


/**
 * Resolve the best success message from a payload.
 *
 * @param {Object} data
 * @param {string} fallback
 * @returns {string}
 */
export function resolveSuccessMessage(data = {}, fallback = 'Operation completed.') {
    const message = data?.message;

    if (isString(message) && message.trim() !== '') {
        return message;
    }

    return fallback;
}


/**
 * Build a normalized successful submission result.
 *
 * Returned shape:
 * - ok
 * - status
 * - message
 * - redirectUrl
 * - data
 *
 * @param {Object} responseResult
 * @returns {{
 *   ok: true,
 *   status: number,
 *   message: string,
 *   redirectUrl: string|null,
 *   data: Object
 * }}
 */
export function normalizeSuccessResult(responseResult = {}) {
    const data = isObject(responseResult?.data) ? responseResult.data : {};
    const status = Number(responseResult?.status ?? data?.status ?? 200);

    return {
        ok: true,
        status,
        message: resolveSuccessMessage(data),
        redirectUrl: resolveSuccessRedirectUrl(data),
        data,
    };
}


/**
 * Apply a successful submission result to shared form state.
 *
 * State updates:
 * - marks submission as completed
 * - stores the normalized result
 * - clears stale field errors
 * - stores top-level success message
 * - stores small meta diagnostics for downstream consumers
 *
 * @param {Object} formState
 * @param {Object} normalizedResult
 * @returns {Object}
 */
export function applySuccessState(formState, normalizedResult) {
    setSubmitting(formState, false);
    setLastResult(formState, normalizedResult);
    clearFieldErrors(formState);
    clearMessage(formState);
    setMessage(formState, normalizedResult.message);
    setMeta(formState, {
        status: normalizedResult.status,
        outcome: 'success',
        redirectUrl: normalizedResult.redirectUrl,
    });

    return formState;
}


/**
 * Handle a successful form submission.
 *
 * Responsibilities:
 * - Normalize the response shape.
 * - Update shared state.
 * - Dispatch the configured AuthKit form success event.
 *
 * @param {Object} context
 * @param {HTMLFormElement} form
 * @param {Object} formState
 * @param {Object} responseResult
 * @returns {{
 *   ok: true,
 *   status: number,
 *   message: string,
 *   redirectUrl: string|null,
 *   data: Object
 * }}
 */
export function handleSuccess(context, form, formState, responseResult = {}) {
    const normalizedResult = normalizeSuccessResult(responseResult);

    applySuccessState(formState, normalizedResult);

    const eventDetail = {
        form,
        status: normalizedResult.status,
        message: normalizedResult.message,
        redirectUrl: normalizedResult.redirectUrl,
        result: normalizedResult.data,
    };

    if (context && isFunction(context.emit)) {
        context.emit('form_success', eventDetail);
    } else {
        dispatchEvent('form_success', eventDetail);
    }

    return normalizedResult;
}