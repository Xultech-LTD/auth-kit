/**
 * AuthKit
 * -----------------------------------------------------------------------------
 * File: tests/js/modules/forms/serialize.test.js
 * Author: Michael Erastus
 * Package: AuthKit
 *
 * Description:
 * -----------------------------------------------------------------------------
 * Unit tests for AuthKit AJAX form serialization utilities.
 *
 * Responsibilities:
 * - Verify form element detection.
 * - Verify form method and action resolution.
 * - Verify FormData serialization.
 * - Verify object serialization and repeated field handling.
 * - Verify serialized form descriptor building.
 * - Verify serialized payload helper utilities.
 */

import { beforeEach, describe, expect, it, vi } from 'vitest';

import {
    buildSerializedForm,
    formDataToObject,
    getCurrentUrl,
    getFormAction,
    getFormMethod,
    getSerializedValue,
    hasSerializedValue,
    isFormElement,
    mergeSerializedData,
    serializeForm,
    serializeFormToFormData,
} from '../../../../public/authkit/js/modules/forms/serialize.js';


describe('modules/forms/serialize', () => {
    beforeEach(() => {
        document.body.innerHTML = '';
    });

    describe('isFormElement', () => {
        it('detects valid form elements', () => {
            const form = document.createElement('form');
            const div = document.createElement('div');

            expect(isFormElement(form)).toBe(true);
            expect(isFormElement(div)).toBe(false);
            expect(isFormElement(null)).toBe(false);
            expect(isFormElement(undefined)).toBe(false);
        });
    });

    describe('getCurrentUrl', () => {
        it('returns the current browser url', () => {
            expect(getCurrentUrl()).toBe(window.location.href);
        });
    });

    describe('getFormMethod', () => {
        it('returns GET for invalid form input', () => {
            expect(getFormMethod(null)).toBe('GET');
            expect(getFormMethod(document.createElement('div'))).toBe('GET');
        });

        it('returns the form method in uppercase', () => {
            const form = document.createElement('form');
            form.method = 'post';

            expect(getFormMethod(form)).toBe('POST');
        });

        it('uses the browser-resolved form method for unsupported method attributes', () => {
            const form = document.createElement('form');
            form.setAttribute('method', 'patch');

            expect(getFormMethod(form)).toBe('GET');
        });

        it('falls back to GET when method is blank', () => {
            const form = document.createElement('form');
            form.setAttribute('method', '   ');

            expect(getFormMethod(form)).toBe('GET');
        });
    });

    describe('getFormAction', () => {
        it('returns current url for invalid form input', () => {
            expect(getFormAction(null)).toBe(window.location.href);
            expect(getFormAction(document.createElement('div'))).toBe(window.location.href);
        });

        it('returns form.action when present', () => {
            const form = document.createElement('form');
            form.action = 'https://example.com/login';

            expect(getFormAction(form)).toBe('https://example.com/login');
        });

        it('falls back to action attribute when present', () => {
            const form = document.createElement('form');
            form.setAttribute('action', '/register');

            expect(getFormAction(form)).toContain('/register');
        });

        it('falls back to current url when action is blank', () => {
            const form = document.createElement('form');
            form.setAttribute('action', '   ');

            expect(getFormAction(form)).toBe(window.location.href);
        });
    });

    describe('serializeFormToFormData', () => {
        it('returns empty FormData for invalid input', () => {
            const resultA = serializeFormToFormData(null);
            const resultB = serializeFormToFormData(document.createElement('div'));

            expect(resultA).toBeInstanceOf(FormData);
            expect(resultB).toBeInstanceOf(FormData);
            expect(Array.from(resultA.entries())).toEqual([]);
            expect(Array.from(resultB.entries())).toEqual([]);
        });

        it('serializes a valid form into FormData', () => {
            const form = document.createElement('form');

            const email = document.createElement('input');
            email.name = 'email';
            email.value = 'michael@example.com';

            const password = document.createElement('input');
            password.name = 'password';
            password.value = 'secret';

            form.append(email, password);

            const result = serializeFormToFormData(form);

            expect(result).toBeInstanceOf(FormData);
            expect(Array.from(result.entries())).toEqual([
                ['email', 'michael@example.com'],
                ['password', 'secret'],
            ]);
        });

        it('ignores disabled fields via native FormData behavior', () => {
            const form = document.createElement('form');

            const enabled = document.createElement('input');
            enabled.name = 'email';
            enabled.value = 'michael@example.com';

            const disabled = document.createElement('input');
            disabled.name = 'token';
            disabled.value = 'abc123';
            disabled.disabled = true;

            form.append(enabled, disabled);

            const result = serializeFormToFormData(form);

            expect(Array.from(result.entries())).toEqual([
                ['email', 'michael@example.com'],
            ]);
        });
    });

    describe('formDataToObject', () => {
        it('returns empty object for invalid input', () => {
            expect(formDataToObject(null)).toEqual({});
            expect(formDataToObject({})).toEqual({});
            expect(formDataToObject([])).toEqual({});
        });

        it('converts FormData into a plain object', () => {
            const formData = new FormData();
            formData.append('email', 'michael@example.com');
            formData.append('password', 'secret');

            expect(formDataToObject(formData)).toEqual({
                email: 'michael@example.com',
                password: 'secret',
            });
        });

        it('groups repeated field names into arrays', () => {
            const formData = new FormData();
            formData.append('roles', 'admin');
            formData.append('roles', 'editor');
            formData.append('roles', 'viewer');

            expect(formDataToObject(formData)).toEqual({
                roles: ['admin', 'editor', 'viewer'],
            });
        });

        it('ignores blank field names', () => {
            const formData = new FormData();
            formData.append('', 'ignored');
            formData.append('email', 'michael@example.com');

            expect(formDataToObject(formData)).toEqual({
                email: 'michael@example.com',
            });
        });
    });

    describe('serializeForm', () => {
        it('serializes a form into a normalized object payload', () => {
            const form = document.createElement('form');

            const email = document.createElement('input');
            email.name = 'email';
            email.value = 'michael@example.com';

            const remember = document.createElement('input');
            remember.type = 'checkbox';
            remember.name = 'remember';
            remember.value = '1';
            remember.checked = true;

            form.append(email, remember);

            expect(serializeForm(form)).toEqual({
                email: 'michael@example.com',
                remember: '1',
            });
        });

        it('groups repeated field names into arrays when serializing a form', () => {
            const form = document.createElement('form');

            const roleA = document.createElement('input');
            roleA.name = 'roles';
            roleA.value = 'admin';

            const roleB = document.createElement('input');
            roleB.name = 'roles';
            roleB.value = 'editor';

            form.append(roleA, roleB);

            expect(serializeForm(form)).toEqual({
                roles: ['admin', 'editor'],
            });
        });
    });

    describe('buildSerializedForm', () => {
        it('builds a normalized submission descriptor for a valid form', () => {
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

            const result = buildSerializedForm(form);

            expect(result.form).toBe(form);
            expect(result.action).toBe('https://example.com/login');
            expect(result.method).toBe('POST');
            expect(result.formData).toBeInstanceOf(FormData);
            expect(result.data).toEqual({
                email: 'michael@example.com',
                password: 'secret',
            });
        });

        it('normalizes invalid form input into safe defaults', () => {
            const result = buildSerializedForm(null);

            expect(result.form).toBeNull();
            expect(result.action).toBe(window.location.href);
            expect(result.method).toBe('GET');
            expect(result.formData).toBeInstanceOf(FormData);
            expect(result.data).toEqual({});
        });
    });

    describe('mergeSerializedData', () => {
        it('merges extra values into a base payload', () => {
            const result = mergeSerializedData(
                { email: 'michael@example.com', remember: '0' },
                { remember: '1', token: 'abc123' }
            );

            expect(result).toEqual({
                email: 'michael@example.com',
                remember: '1',
                token: 'abc123',
            });
        });

        it('uses empty objects for invalid payloads', () => {
            expect(mergeSerializedData(null, { token: 'abc123' })).toEqual({
                token: 'abc123',
            });

            expect(mergeSerializedData({ email: 'michael@example.com' }, null)).toEqual({
                email: 'michael@example.com',
            });

            expect(mergeSerializedData(null, null)).toEqual({});
        });
    });

    describe('getSerializedValue', () => {
        it('returns the value for an existing field', () => {
            const payload = {
                email: 'michael@example.com',
                roles: ['admin', 'editor'],
            };

            expect(getSerializedValue(payload, 'email')).toBe('michael@example.com');
            expect(getSerializedValue(payload, 'roles')).toEqual(['admin', 'editor']);
        });

        it('returns fallback for missing or invalid fields', () => {
            const payload = {
                email: 'michael@example.com',
            };

            expect(getSerializedValue(payload, 'missing', 'fallback')).toBe('fallback');
            expect(getSerializedValue(payload, '', 'fallback')).toBe('fallback');
            expect(getSerializedValue(null, 'email', 'fallback')).toBe('fallback');
        });
    });

    describe('hasSerializedValue', () => {
        it('returns true when the field exists on the payload', () => {
            const payload = {
                email: 'michael@example.com',
                remember: false,
                count: 0,
            };

            expect(hasSerializedValue(payload, 'email')).toBe(true);
            expect(hasSerializedValue(payload, 'remember')).toBe(true);
            expect(hasSerializedValue(payload, 'count')).toBe(true);
        });

        it('returns false for missing or invalid fields', () => {
            const payload = {
                email: 'michael@example.com',
            };

            expect(hasSerializedValue(payload, 'missing')).toBe(false);
            expect(hasSerializedValue(payload, '')).toBe(false);
            expect(hasSerializedValue(null, 'email')).toBe(false);
        });
    });
});