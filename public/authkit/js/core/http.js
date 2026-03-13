/**
 * AuthKit
 * -----------------------------------------------------------------------------
 * File: js/core/http.js
 * Author: Michael Erastus
 * Package: AuthKit
 *
 * Description:
 * -----------------------------------------------------------------------------
 * Lightweight HTTP utilities for the AuthKit browser runtime.
 *
 * This file centralizes low-level request behavior used by runtime modules,
 * especially the AJAX forms module, while keeping transport concerns isolated
 * from UI behavior and page-specific logic.
 *
 * Responsibilities:
 * - Build standard AuthKit request headers.
 * - Resolve CSRF tokens from the current document.
 * - Normalize fetch options for JSON or FormData requests.
 * - Perform browser requests using the Fetch API.
 * - Parse responses safely across common response types.
 *
 * Design notes:
 * - This layer must remain framework-agnostic except for optional Laravel-
 *   friendly conveniences such as X-CSRF-TOKEN and X-Requested-With headers.
 * - This file should not contain form-state logic or UI rendering logic.
 * - This file should not dispatch AuthKit browser events directly.
 *
 * @package   AuthKit
 * @author    Michael Erastus
 * @license   MIT
 */

import { queryOne } from './dom.js';
import { dataGet, isObject, isString, normalizeString } from './helpers.js';


/**
 * Resolve the CSRF token from the current document.
 *
 * Expected source:
 * - <meta name="csrf-token" content="...">
 *
 * @returns {string|null}
 */
export function getCsrfToken() {
    const meta = queryOne('meta[name="csrf-token"]');

    if (!meta) {
        return null;
    }

    const token = meta.getAttribute('content');

    return normalizeString(token, null);
}


/**
 * Determine whether a header bag contains a given header name.
 *
 * Header names are compared case-insensitively.
 *
 * @param {Record<string, string>} headers
 * @param {string} name
 * @returns {boolean}
 */
export function hasHeader(headers, name) {
    if (!isObject(headers) || !isString(name) || name.trim() === '') {
        return false;
    }

    const expected = name.trim().toLowerCase();

    return Object.keys(headers).some((key) => key.toLowerCase() === expected);
}


/**
 * Normalize a plain-object header bag.
 *
 * Non-string header names are ignored.
 * Nullish values are omitted.
 *
 * @param {Object} [headers={}]
 * @returns {Record<string, string>}
 */
export function normalizeHeaders(headers = {}) {
    if (!isObject(headers)) {
        return {};
    }

    return Object.entries(headers).reduce((carry, [key, value]) => {
        if (!isString(key) || key.trim() === '') {
            return carry;
        }

        if (value === null || value === undefined) {
            return carry;
        }

        carry[key.trim()] = String(value);

        return carry;
    }, {});
}


/**
 * Build the standard AuthKit request headers.
 *
 * Default headers:
 * - Accept: application/json
 * - X-Requested-With: XMLHttpRequest
 * - X-CSRF-TOKEN: <meta csrf token>, when available
 *
 * Content-Type is intentionally not forced here because:
 * - JSON requests need application/json
 * - FormData requests should allow the browser to define multipart boundaries
 *
 * @param {Object} [overrides={}]
 * @returns {Record<string, string>}
 */
export function buildHeaders(overrides = {}) {
    const headers = normalizeHeaders({
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    });

    const csrfToken = getCsrfToken();

    if (csrfToken !== null && !hasHeader(headers, 'X-CSRF-TOKEN')) {
        headers['X-CSRF-TOKEN'] = csrfToken;
    }

    return {
        ...headers,
        ...normalizeHeaders(overrides),
    };
}


/**
 * Build a JSON request body payload.
 *
 * @param {*} data
 * @returns {string}
 */
export function buildJsonBody(data) {
    return JSON.stringify(data ?? {});
}


/**
 * Build a FormData instance from a supported payload.
 *
 * Supported values:
 * - existing FormData
 * - HTMLFormElement
 * - plain object
 *
 * @param {*} payload
 * @returns {FormData}
 */
export function buildFormData(payload) {
    if (payload instanceof FormData) {
        return payload;
    }

    if (payload instanceof HTMLFormElement) {
        return new FormData(payload);
    }

    const formData = new FormData();

    if (!isObject(payload)) {
        return formData;
    }

    Object.entries(payload).forEach(([key, value]) => {
        if (!isString(key) || key.trim() === '') {
            return;
        }

        if (Array.isArray(value)) {
            value.forEach((item) => {
                if (isObject(item)) {
                    return;
                }

                formData.append(key, item ?? '');
            });

            return;
        }

        if (isObject(value)) {
            return;
        }

        formData.append(key, value ?? '');
    });

    return formData;
}

/**
 * Determine whether the provided response advertises JSON content.
 *
 * @param {Response} response
 * @returns {boolean}
 */
export function isJsonResponse(response) {
    const contentType = response?.headers?.get('content-type') ?? '';

    return contentType.toLowerCase().includes('application/json');
}


/**
 * Safely parse a fetch Response into a normalized payload.
 *
 * Parsing strategy:
 * - JSON when content-type indicates JSON
 * - otherwise text
 *
 * @param {Response} response
 * @returns {Promise<*>}
 */
export async function parseResponseBody(response) {
    if (!response) {
        return null;
    }

    if (response.status === 204) {
        return null;
    }

    if (isJsonResponse(response)) {
        try {
            return await response.json();
        } catch (error) {
            return null;
        }
    }

    try {
        return await response.text();
    } catch (error) {
        return null;
    }
}


/**
 * Normalize a fetch Response into a stable result object.
 *
 * @param {Response} response
 * @returns {Promise<Object>}
 */
export async function normalizeResponse(response) {
    const data = await parseResponseBody(response);

    return {
        ok: response.ok,
        status: response.status,
        statusText: response.statusText,
        redirected: response.redirected,
        url: response.url,
        headers: response.headers,
        data,
        response,
    };
}


/**
 * Build fetch options for an AuthKit request.
 *
 * Supported option keys:
 * - method
 * - headers
 * - body
 * - credentials
 * - signal
 * - mode
 * - redirect
 *
 * Custom AuthKit keys:
 * - asJson: boolean
 *
 * @param {Object} [options={}]
 * @returns {RequestInit}
 */
export function buildRequestOptions(options = {}) {
    const method = normalizeString(dataGet(options, 'method', 'GET'), 'GET').toUpperCase();
    const asJson = Boolean(dataGet(options, 'asJson', false));
    const credentials = dataGet(options, 'credentials', 'same-origin');
    const signal = dataGet(options, 'signal', undefined);
    const mode = dataGet(options, 'mode', undefined);
    const redirect = dataGet(options, 'redirect', undefined);
    const rawHeaders = normalizeHeaders(dataGet(options, 'headers', {}));
    const rawBody = dataGet(options, 'body', null);

    const headers = buildHeaders(rawHeaders);

    let body = undefined;

    if (method !== 'GET' && method !== 'HEAD') {
        if (asJson) {
            if (!hasHeader(headers, 'Content-Type')) {
                headers['Content-Type'] = 'application/json';
            }

            body = buildJsonBody(rawBody);
        } else if (rawBody !== null && rawBody !== undefined) {
            body = buildFormData(rawBody);
        }
    }

    return {
        method,
        headers,
        body,
        credentials,
        signal,
        mode,
        redirect,
    };
}


/**
 * Perform a fetch request and return a normalized response object.
 *
 * @param {string} url
 * @param {Object} [options={}]
 * @returns {Promise<Object>}
 */
export async function request(url, options = {}) {
    const normalizedUrl = normalizeString(url, '');

    if (normalizedUrl === '') {
        throw new Error('AuthKit HTTP request requires a valid URL.');
    }

    const fetchOptions = buildRequestOptions(options);
    const response = await fetch(normalizedUrl, fetchOptions);

    return normalizeResponse(response);
}


/**
 * Perform a GET request.
 *
 * @param {string} url
 * @param {Object} [options={}]
 * @returns {Promise<Object>}
 */
export function get(url, options = {}) {
    return request(url, {
        ...options,
        method: 'GET',
    });
}


/**
 * Perform a POST request.
 *
 * @param {string} url
 * @param {*} [body=null]
 * @param {Object} [options={}]
 * @returns {Promise<Object>}
 */
export function post(url, body = null, options = {}) {
    return request(url, {
        ...options,
        method: 'POST',
        body,
    });
}


/**
 * Perform a JSON POST request.
 *
 * @param {string} url
 * @param {*} [body=null]
 * @param {Object} [options={}]
 * @returns {Promise<Object>}
 */
export function postJson(url, body = null, options = {}) {
    return request(url, {
        ...options,
        method: 'POST',
        body,
        asJson: true,
    });
}