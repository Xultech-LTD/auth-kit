/**
 * AuthKit
 * -----------------------------------------------------------------------------
 * File: tests/js/modules/forms/bind-forms.test.js
 * Author: Michael Erastus
 * Package: AuthKit
 *
 * Description:
 * -----------------------------------------------------------------------------
 * Unit tests for AuthKit AJAX form binding utilities.
 *
 * Responsibilities:
 * - Verify single-form binding behavior.
 * - Verify duplicate-binding protection.
 * - Verify cleanup and unbinding behavior.
 * - Verify bulk binding helpers.
 * - Verify runtime boot wiring into submitForm.
 */

import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('../../../../resources/js/authkit/core/dom.js', async () => {
    const actual = await vi.importActual('../../../../resources/js/authkit/core/dom.js');

    return {
        ...actual,
        getAjaxForms: vi.fn(),
    };
});

vi.mock('../../../../resources/js/authkit/modules/forms/submit.js', () => ({
    submitForm: vi.fn(),
}));

import { getAjaxForms } from '../../../../resources/js/authkit/core/dom.js';
import { submitForm } from '../../../../resources/js/authkit/modules/forms/submit.js';

import {
    bindForm,
    bindForms,
    bootForms,
    getFormBinding,
    isFormBound,
    isFormElement,
    rebindForm,
    unbindForm,
    unbindForms,
} from '../../../../resources/js/authkit/modules/forms/bind-forms.js';


describe('modules/forms/bind-forms', () => {
    beforeEach(() => {
        document.body.innerHTML = '';
        vi.clearAllMocks();
    });

    it('detects valid form elements', () => {
        const form = document.createElement('form');
        const div = document.createElement('div');

        expect(isFormElement(form)).toBe(true);
        expect(isFormElement(div)).toBe(false);
        expect(isFormElement(null)).toBe(false);
        expect(isFormElement(undefined)).toBe(false);
    });

    it('returns false for unbound or invalid forms', () => {
        const form = document.createElement('form');

        expect(isFormBound(form)).toBe(false);
        expect(isFormBound(null)).toBe(false);
        expect(isFormBound(document.createElement('div'))).toBe(false);
    });

    it('binds a submit handler to a single form', () => {
        const form = document.createElement('form');
        const handler = vi.fn();

        const cleanup = bindForm(form, handler);

        expect(typeof cleanup).toBe('function');
        expect(isFormBound(form)).toBe(true);

        const event = new Event('submit', { bubbles: true, cancelable: true });
        form.dispatchEvent(event);

        expect(handler).toHaveBeenCalledTimes(1);

        const [receivedEvent, receivedForm, receivedState] = handler.mock.calls[0];

        expect(receivedEvent).toBe(event);
        expect(receivedForm).toBe(form);
        expect(receivedState).toBeTruthy();
        expect(receivedState.form).toBe(form);
        expect(receivedState.submitting).toBe(false);
        expect(receivedState.submitted).toBe(false);
        expect(receivedState.lastResult).toBeNull();
        expect(receivedState.fieldErrors).toEqual({});
        expect(receivedState.message).toBeNull();
        expect(receivedState.meta).toEqual({});
    });

    it('prevents default browser submit behavior', () => {
        const form = document.createElement('form');
        const handler = vi.fn();

        bindForm(form, handler);

        const event = new Event('submit', { bubbles: true, cancelable: true });
        const preventDefaultSpy = vi.spyOn(event, 'preventDefault');

        form.dispatchEvent(event);

        expect(preventDefaultSpy).toHaveBeenCalledTimes(1);
        expect(handler).toHaveBeenCalledTimes(1);
    });

    it('returns noop cleanup for invalid bind input', () => {
        const cleanupA = bindForm(null, vi.fn());
        const cleanupB = bindForm(document.createElement('div'), vi.fn());
        const cleanupC = bindForm(document.createElement('form'), null);

        expect(typeof cleanupA).toBe('function');
        expect(typeof cleanupB).toBe('function');
        expect(typeof cleanupC).toBe('function');

        expect(() => cleanupA()).not.toThrow();
        expect(() => cleanupB()).not.toThrow();
        expect(() => cleanupC()).not.toThrow();
    });

    it('does not duplicate binding for the same form', () => {
        const form = document.createElement('form');
        const handlerA = vi.fn();
        const handlerB = vi.fn();

        const cleanupA = bindForm(form, handlerA);
        const cleanupB = bindForm(form, handlerB);

        expect(cleanupB).toBe(cleanupA);
        expect(isFormBound(form)).toBe(true);

        form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));

        expect(handlerA).toHaveBeenCalledTimes(1);
        expect(handlerB).not.toHaveBeenCalled();
    });

    it('stores and resolves the binding entry for a form', () => {
        const form = document.createElement('form');
        const handler = vi.fn();

        bindForm(form, handler);

        const binding = getFormBinding(form);

        expect(binding).not.toBeNull();
        expect(typeof binding.handler).toBe('function');
        expect(typeof binding.cleanup).toBe('function');
        expect(binding.state).toBeTruthy();
        expect(binding.state.form).toBe(form);
    });

    it('returns null binding for invalid input', () => {
        expect(getFormBinding(null)).toBeNull();
        expect(getFormBinding(document.createElement('div'))).toBeNull();
    });

    it('unbinds a bound form', () => {
        const form = document.createElement('form');
        const handler = vi.fn();

        bindForm(form, handler);

        expect(isFormBound(form)).toBe(true);

        const removed = unbindForm(form);

        expect(removed).toBe(true);
        expect(isFormBound(form)).toBe(false);
        expect(getFormBinding(form)).toBeNull();

        form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));

        expect(handler).not.toHaveBeenCalled();
    });

    it('returns false when unbinding an unbound or invalid form', () => {
        const form = document.createElement('form');

        expect(unbindForm(form)).toBe(false);
        expect(unbindForm(null)).toBe(false);
        expect(unbindForm(document.createElement('div'))).toBe(false);
    });

    it('cleanup returned by bindForm unbinds the form', () => {
        const form = document.createElement('form');
        const handler = vi.fn();

        const cleanup = bindForm(form, handler);

        expect(isFormBound(form)).toBe(true);

        cleanup();

        expect(isFormBound(form)).toBe(false);

        form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));

        expect(handler).not.toHaveBeenCalled();
    });

    it('binds multiple forms with one handler', () => {
        const formA = document.createElement('form');
        const formB = document.createElement('form');
        const handler = vi.fn();

        const cleanup = bindForms([formA, formB], handler);

        formA.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
        formB.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));

        expect(handler).toHaveBeenCalledTimes(2);
        expect(isFormBound(formA)).toBe(true);
        expect(isFormBound(formB)).toBe(true);

        cleanup();

        expect(isFormBound(formA)).toBe(false);
        expect(isFormBound(formB)).toBe(false);
    });

    it('ignores invalid forms in bulk binding', () => {
        const form = document.createElement('form');
        const handler = vi.fn();

        const cleanup = bindForms([form, null, document.createElement('div')], handler);

        form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));

        expect(handler).toHaveBeenCalledTimes(1);

        cleanup();

        expect(isFormBound(form)).toBe(false);
    });

    it('returns noop cleanup for invalid bulk binding input', () => {
        const cleanupA = bindForms(null, vi.fn());
        const cleanupB = bindForms([], null);

        expect(typeof cleanupA).toBe('function');
        expect(typeof cleanupB).toBe('function');

        expect(() => cleanupA()).not.toThrow();
        expect(() => cleanupB()).not.toThrow();
    });

    it('unbinds multiple forms and returns removed count', () => {
        const formA = document.createElement('form');
        const formB = document.createElement('form');
        const formC = document.createElement('form');

        bindForm(formA, vi.fn());
        bindForm(formB, vi.fn());

        const removed = unbindForms([formA, formB, formC, null]);

        expect(removed).toBe(2);
        expect(isFormBound(formA)).toBe(false);
        expect(isFormBound(formB)).toBe(false);
        expect(isFormBound(formC)).toBe(false);
    });

    it('returns zero when unbinding multiple invalid inputs', () => {
        expect(unbindForms(null)).toBe(0);
        expect(unbindForms([null, document.createElement('div')])).toBe(0);
    });

    it('rebinds a form with a fresh handler', () => {
        const form = document.createElement('form');
        const handlerA = vi.fn();
        const handlerB = vi.fn();

        bindForm(form, handlerA);
        rebindForm(form, handlerB);

        form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));

        expect(handlerA).not.toHaveBeenCalled();
        expect(handlerB).toHaveBeenCalledTimes(1);
    });

    it('rebind creates a fresh binding state object', () => {
        const form = document.createElement('form');
        const handlerA = vi.fn();
        const handlerB = vi.fn();

        bindForm(form, handlerA);

        const firstBinding = getFormBinding(form);

        rebindForm(form, handlerB);

        const secondBinding = getFormBinding(form);

        expect(firstBinding).not.toBeNull();
        expect(secondBinding).not.toBeNull();
        expect(secondBinding).not.toBe(firstBinding);
        expect(secondBinding.state).not.toBe(firstBinding.state);
        expect(secondBinding.state.form).toBe(form);
    });

    it('returns noop cleanup when rebinding invalid input', () => {
        const cleanupA = rebindForm(null, vi.fn());
        const cleanupB = rebindForm(document.createElement('div'), vi.fn());
        const cleanupC = rebindForm(document.createElement('form'), null);

        expect(typeof cleanupA).toBe('function');
        expect(typeof cleanupB).toBe('function');
        expect(typeof cleanupC).toBe('function');

        expect(() => cleanupA()).not.toThrow();
        expect(() => cleanupB()).not.toThrow();
        expect(() => cleanupC()).not.toThrow();
    });

    it('boots ajax forms and wires them into submitForm', () => {
        const formA = document.createElement('form');
        const formB = document.createElement('form');

        getAjaxForms.mockReturnValue([formA, formB]);

        const context = {
            config: {
                forms: {
                    ajaxAttribute: 'data-authkit-ajax',
                },
            },
        };

        const booted = bootForms(context);

        expect(getAjaxForms).toHaveBeenCalledTimes(1);
        expect(getAjaxForms).toHaveBeenCalledWith('data-authkit-ajax');
        expect(booted.forms).toEqual([formA, formB]);
        expect(booted.count).toBe(2);
        expect(typeof booted.cleanup).toBe('function');

        const eventA = new Event('submit', { bubbles: true, cancelable: true });
        formA.dispatchEvent(eventA);

        expect(submitForm).toHaveBeenCalledTimes(1);
        expect(submitForm).toHaveBeenCalledWith(
            context,
            formA,
            expect.objectContaining({
                form: formA,
                submitting: false,
                submitted: false,
                lastResult: null,
                fieldErrors: {},
                message: null,
                meta: {},
            }),
            { event: eventA }
        );
    });

    it('falls back to nested ajax attribute config during boot', () => {
        const form = document.createElement('form');

        getAjaxForms.mockReturnValue([form]);

        const context = {
            config: {
                forms: {
                    ajax: {
                        attribute: 'data-custom-ajax',
                    },
                },
            },
        };

        const booted = bootForms(context);

        expect(getAjaxForms).toHaveBeenCalledWith('data-custom-ajax');
        expect(booted.forms).toEqual([form]);
        expect(booted.count).toBe(1);
    });

    it('falls back to default ajax attribute during boot', () => {
        getAjaxForms.mockReturnValue([]);

        const booted = bootForms({});

        expect(getAjaxForms).toHaveBeenCalledWith('data-authkit-ajax');
        expect(booted.forms).toEqual([]);
        expect(booted.count).toBe(0);
        expect(typeof booted.cleanup).toBe('function');
    });

    it('boot cleanup unbinds all boot-bound forms', () => {
        const formA = document.createElement('form');
        const formB = document.createElement('form');

        getAjaxForms.mockReturnValue([formA, formB]);

        const booted = bootForms({});

        expect(isFormBound(formA)).toBe(true);
        expect(isFormBound(formB)).toBe(true);

        booted.cleanup();

        expect(isFormBound(formA)).toBe(false);
        expect(isFormBound(formB)).toBe(false);
    });
});