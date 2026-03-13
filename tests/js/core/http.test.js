/**
 * AuthKit
 * -----------------------------------------------------------------------------
 * File: tests/js/core/http.test.js
 * Author: Michael Erastus
 * Package: AuthKit
 *
 * Description:
 * -----------------------------------------------------------------------------
 * Unit tests for AuthKit HTTP utilities.
 *
 * Responsibilities:
 * - Verify CSRF token resolution.
 * - Verify header normalization and standard header building.
 * - Verify JSON and FormData body building.
 * - Verify response parsing and normalization.
 * - Verify request option building and fetch helpers.
 */

import { beforeEach, describe, expect, it, vi } from 'vitest';

import {
    buildFormData,
    buildHeaders,
    buildJsonBody,
    buildRequestOptions,
    get,
    getCsrfToken,
    hasHeader,
    isJsonResponse,
    normalizeHeaders,
    normalizeResponse,
    parseResponseBody,
    post,
    postJson,
    request,
} from '../../../resources/js/authkit/core/http.js';

import { resetCoreTestEnvironment } from './support/core-test-helpers.js';


describe('core/http', () => {
    beforeEach(() => {
        resetCoreTestEnvironment();

        const meta = document.createElement('meta');
        meta.setAttribute('name', 'csrf-token');
        meta.setAttribute('content', 'csrf-123');

        document.head.appendChild(meta);
    });

    it('resolves the CSRF token from the document head', () => {
        expect(getCsrfToken()).toBe('csrf-123');
    });

    it('detects and normalizes headers safely', () => {
        expect(hasHeader({ Accept: 'application/json' }, 'accept')).toBe(true);
        expect(normalizeHeaders({ Accept: 'application/json', Empty: null })).toEqual({
            Accept: 'application/json',
        });
    });

    it('builds standard request headers including csrf token', () => {
        const headers = buildHeaders();

        expect(headers.Accept).toBe('application/json');
        expect(headers['X-Requested-With']).toBe('XMLHttpRequest');
        expect(headers['X-CSRF-TOKEN']).toBe('csrf-123');
    });

    it('builds JSON request bodies correctly', () => {
        expect(buildJsonBody({ a: 1 })).toBe('{"a":1}');
    });

    it('builds FormData from plain objects and forms', () => {
        const formData = buildFormData({
            email: 'test@example.com',
            roles: ['admin', 'editor'],
        });

        expect(formData.get('email')).toBe('test@example.com');
        expect(formData.getAll('roles')).toEqual(['admin', 'editor']);

        document.body.innerHTML = `
            <form id="login-form">
                <input name="email" value="michael@example.com">
            </form>
        `;

        const form = document.getElementById('login-form');
        const formPayload = buildFormData(form);

        expect(formPayload.get('email')).toBe('michael@example.com');
    });

    it('detects JSON responses', () => {
        const response = new Response(JSON.stringify({ ok: true }), {
            headers: {
                'content-type': 'application/json',
            },
        });

        expect(isJsonResponse(response)).toBe(true);
    });

    it('parses response bodies safely', async () => {
        const jsonResponse = new Response(JSON.stringify({ ok: true }), {
            headers: {
                'content-type': 'application/json',
            },
        });

        const textResponse = new Response('plain text', {
            headers: {
                'content-type': 'text/plain',
            },
        });

        expect(await parseResponseBody(jsonResponse)).toEqual({ ok: true });
        expect(await parseResponseBody(textResponse)).toBe('plain text');
    });

    it('normalizes fetch responses into a stable result object', async () => {
        const response = new Response(JSON.stringify({ ok: true }), {
            status: 200,
            statusText: 'OK',
            headers: {
                'content-type': 'application/json',
            },
        });

        const normalized = await normalizeResponse(response);

        expect(normalized.ok).toBe(true);
        expect(normalized.status).toBe(200);
        expect(normalized.data).toEqual({ ok: true });
    });

    it('builds fetch request options for json and form payloads', () => {
        const jsonOptions = buildRequestOptions({
            method: 'POST',
            body: { email: 'test@example.com' },
            asJson: true,
        });

        expect(jsonOptions.method).toBe('POST');
        expect(jsonOptions.headers['Content-Type']).toBe('application/json');
        expect(jsonOptions.body).toBe('{"email":"test@example.com"}');

        const formOptions = buildRequestOptions({
            method: 'POST',
            body: { email: 'test@example.com' },
        });

        expect(formOptions.method).toBe('POST');
        expect(formOptions.body instanceof FormData).toBe(true);
    });

    it('omits nullish optional transport values from fetch options', () => {
        const options = buildRequestOptions({
            method: 'POST',
            body: { email: 'test@example.com' },
            mode: null,
            redirect: null,
            signal: null,
        });

        expect(options).not.toHaveProperty('mode');
        expect(options).not.toHaveProperty('redirect');
        expect(options).not.toHaveProperty('signal');
        expect(options.credentials).toBe('same-origin');
    });

    it('preserves valid optional transport values in fetch options', () => {
        const controller = new AbortController();

        const options = buildRequestOptions({
            method: 'POST',
            body: { email: 'test@example.com' },
            mode: 'cors',
            redirect: 'manual',
            signal: controller.signal,
        });

        expect(options.mode).toBe('cors');
        expect(options.redirect).toBe('manual');
        expect(options.signal).toBe(controller.signal);
    });

    it('performs normalized fetch requests through helper methods', async () => {
        const fetchSpy = vi.spyOn(globalThis, 'fetch').mockResolvedValue(
            new Response(JSON.stringify({ success: true }), {
                status: 200,
                headers: {
                    'content-type': 'application/json',
                },
            })
        );

        const result = await request('/login', {
            method: 'POST',
            body: { email: 'test@example.com' },
            asJson: true,
        });

        expect(fetchSpy).toHaveBeenCalledTimes(1);
        expect(result.ok).toBe(true);
        expect(result.data).toEqual({ success: true });

        await get('/status');
        await post('/login', { email: 'x' });
        await postJson('/login', { email: 'x' });

        expect(fetchSpy).toHaveBeenCalledTimes(4);

        fetchSpy.mockRestore();
    });

    it('throws when request url is invalid', async () => {
        await expect(request('')).rejects.toThrow(
            'AuthKit HTTP request requires a valid URL.'
        );
    });
});