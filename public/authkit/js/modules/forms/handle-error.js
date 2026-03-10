/**
 * AuthKit
 * -----------------------------------------------------------------------------
 * File: handle-error.js
 * Author: Michael Erastus
 * Package: AuthKit
 *
 * Description:
 * -----------------------------------------------------------------------------
 * Error response handling utilities for the AuthKit AJAX forms module.
 *
 * This file is responsible for normalizing failed form submission outcomes into
 * a predictable client-side structure that page modules and UI integrations can
 * consume consistently.
 *
 * Responsibilities:
 * - Normalize failed HTTP response payloads.
 * - Extract top-level failure messages.
 * - Extract structured field validation errors.
 * - Update the shared form state with normalized failure data.
 * - Dispatch AuthKit form error events through the provided runtime context.
 *
 * Design notes:
 * - This file does not perform DOM rendering of errors.
 * - This file does not submit forms directly.
 * - This file assumes page modules or UI adapters may render state externally.
 * - The normalized output must remain stable across validation, domain, and
 *   transport-level failures.
 *
 * Expected backend payload compatibility:
 * - Standard AuthKitActionResult validation failures.
 * - Laravel validation JSON payloads.
 * - Generic JSON error responses.
 * - Transport/network failures converted into synthetic result objects.
 *
 * @package   AuthKit
 * @author    Michael Erastus
 * @license   MIT
 */

import { dispatchEvent } from '../../core/events.js';
import {dataGet, isFunction, isObject, isString} from '../../core/helpers.js';
import { clearMessage, setFieldErrors, setLastResult, setMessage, setMeta, setSubmitting, } from './state.js';


/**
 * Normalize grouped field errors from a response payload.
 *
 * Supported sources:
 * - payload.fields
 * - Laravel-style errors map
 * - structured errors[] entries with "field" and "message"
 *
 * Final shape:
 * - {
 *     email: ['The email field is required.'],
 *     password: ['The password field is required.']
 *   }
 *
 * @param {Object} data
 * @returns {Record<string, string[]>}
 */
export function normalizeFieldErrors(data = {}) {
    const normalized = {};

    const payloadFields = dataGet(data, 'payload.fields', null);

    if (isObject(payloadFields)) {
        Object.entries(payloadFields).forEach(([field, messages]) => {
            if (!isString(field) || field.trim() === '') {
                return;
            }

            normalized[field] = Array.isArray(messages)
                ? messages
                    .map((message) => String(message))
                    .filter((message) => message.trim() !== '')
                : [String(messages)].filter((message) => message.trim() !== '');
        });
    }

    const rawErrors = data?.errors ?? null;

    if (isObject(rawErrors) && !Array.isArray(rawErrors)) {
        Object.entries(rawErrors).forEach(([field, messages]) => {
            if (!isString(field) || field.trim() === '') {
                return;
            }

            if (!normalized[field]) {
                normalized[field] = Array.isArray(messages)
                    ? messages
                        .map((message) => String(message))
                        .filter((message) => message.trim() !== '')
                    : [String(messages)].filter((message) => message.trim() !== '');
            }
        });
    }

    const structuredErrors = Array.isArray(rawErrors) ? rawErrors : [];

    structuredErrors.forEach((error) => {
        const field = isObject(error) ? error.field : null;
        const message = isObject(error) ? error.message : null;

        if (!isString(field) || field.trim() === '') {
            return;
        }

        if (!isString(message) || message.trim() === '') {
            return;
        }

        if (!Array.isArray(normalized[field])) {
            normalized[field] = [];
        }

        normalized[field].push(message);
    });

    return normalized;
}


/**
 * Resolve the best user-facing failure message from a payload.
 *
 * Resolution order:
 * - top-level message
 * - first field error message
 * - fallback message
 *
 * @param {Object} data
 * @param {Record<string, string[]>} fieldErrors
 * @param {string} fallback
 * @returns {string}
 */
export function resolveErrorMessage(data = {}, fieldErrors = {}, fallback = 'Something went wrong.') {
    const topLevelMessage = data?.message;

    if (isString(topLevelMessage) && topLevelMessage.trim() !== '') {
        return topLevelMessage;
    }

    for (const messages of Object.values(fieldErrors)) {
        if (Array.isArray(messages) && messages.length > 0) {
            const firstMessage = messages[0];

            if (isString(firstMessage) && firstMessage.trim() !== '') {
                return firstMessage;
            }
        }
    }

    return fallback;
}


/**
 * Build a normalized failed submission result.
 *
 * Returned shape:
 * - ok
 * - status
 * - message
 * - fieldErrors
 * - data
 *
 * @param {Object} responseResult
 * @returns {{
 *   ok: false,
 *   status: number,
 *   message: string,
 *   fieldErrors: Record<string, string[]>,
 *   data: Object
 * }}
 */
export function normalizeErrorResult(responseResult = {}) {
    const data = isObject(responseResult?.data) ? responseResult.data : {};
    const fieldErrors = normalizeFieldErrors(data);
    const status = Number(responseResult?.status ?? data?.status ?? 422);

    return {
        ok: false,
        status,
        message: resolveErrorMessage(data, fieldErrors),
        fieldErrors,
        data,
    };
}


/**
 * Apply a failed submission result to shared form state.
 *
 * State updates:
 * - marks submission as completed
 * - stores the normalized result
 * - stores grouped field errors
 * - stores top-level message
 * - stores small meta diagnostics for downstream consumers
 *
 * @param {Object} formState
 * @param {Object} normalizedResult
 * @returns {Object}
 */
export function applyErrorState(formState, normalizedResult) {
    setSubmitting(formState, false);
    setLastResult(formState, normalizedResult);
    clearMessage(formState);
    setMessage(formState, normalizedResult.message);
    setFieldErrors(formState, normalizedResult.fieldErrors);
    setMeta(formState, {
        status: normalizedResult.status,
        outcome: 'error',
    });

    return formState;
}


/**
 * Handle a failed form submission.
 *
 * Responsibilities:
 * - Normalize the response shape.
 * - Update shared state.
 * - Dispatch the configured AuthKit form error event.
 *
 * @param {Object} context
 * @param {HTMLFormElement} form
 * @param {Object} formState
 * @param {Object} responseResult
 * @returns {{
 *   ok: false,
 *   status: number,
 *   message: string,
 *   fieldErrors: Record<string, string[]>,
 *   data: Object
 * }}
 */
export function handleError(context, form, formState, responseResult = {}) {
    const normalizedResult = normalizeErrorResult(responseResult);

    applyErrorState(formState, normalizedResult);

    const eventDetail = {
        form,
        status: normalizedResult.status,
        message: normalizedResult.message,
        errors: normalizedResult.fieldErrors,
        result: normalizedResult.data,
    };

    if (context && isFunction(context.emit)) {
        context.emit('form_error', eventDetail);
    } else {
        dispatchEvent('form_error', eventDetail);
    }

    return normalizedResult;
}