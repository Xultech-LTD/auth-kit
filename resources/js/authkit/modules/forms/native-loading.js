/**
 * AuthKit
 * -----------------------------------------------------------------------------
 * File: js/modules/forms/native-loading.js
 * Author: Michael Erastus
 * Package: AuthKit
 *
 * Description:
 * -----------------------------------------------------------------------------
 * Native-submit loading enhancer for AuthKit forms.
 *
 * This file is responsible for applying temporary loading UI to AuthKit forms
 * that submit through the browser's normal HTTP flow.
 *
 * Responsibilities:
 * - Discover whether a form is managed by AJAX or native HTTP submission.
 * - Skip AJAX-managed forms because the AJAX forms module already owns them.
 * - Bind submit listeners to native HTTP forms only.
 * - Apply loading state immediately before the browser continues native submit.
 * - Prevent repeated native submissions while the form is already submitting.
 * - Expose cleanup helpers for unbinding.
 * - Provide a lightweight runtime boot entry for native loading enhancement.
 *
 * Design notes:
 * - This file does not prevent default submission for native HTTP forms.
 * - This file does not perform HTTP requests.
 * - This file does not normalize server responses.
 * - This file only enhances native-submit UX with loading state.
 * - Original page navigation remains fully browser-driven.
 *
 * Binding contract:
 * - A native-bound form receives exactly one active submit listener.
 * - AJAX-managed forms are ignored deliberately.
 * - Rebinding an already bound native form returns the existing cleanup callback.
 * - Cleanup removes the listener and clears internal binding state.
 *
 * @package   AuthKit
 * @author    Michael Erastus
 * @license   MIT
 */

import { getAttribute, getForms } from '../../core/dom.js';
import { dataGet, isFunction, normalizeString } from '../../core/helpers.js';
import { applyLoadingState } from './loading.js';
import { createFormState, isSubmitting, setMeta, setSubmitting } from './state.js';


/**
 * Internal registry of active native-loading bindings.
 *
 * WeakMap is used so detached form elements do not get retained unnecessarily.
 *
 * Stored value shape:
 * - {
 *     handler: Function,
 *     cleanup: Function,
 *     state: Object
 *   }
 *
 * @type {WeakMap<HTMLFormElement, {handler: Function, cleanup: Function, state: Object}>}
 */
const nativeLoadingBindings = new WeakMap();


/**
 * Determine whether the supplied value is a form element.
 *
 * @param {*} value
 * @returns {boolean}
 */
export function isFormElement(value) {
    return value instanceof HTMLFormElement;
}


/**
 * Resolve the configured AJAX attribute used to mark AJAX-managed forms.
 *
 * Resolution order:
 * - context.config.forms.ajaxAttribute
 * - context.config.forms.ajax.attribute
 * - data-authkit-ajax fallback
 *
 * @param {Object|null} context
 * @returns {string}
 */
export function getAjaxAttribute(context = null) {
    return normalizeString(
        dataGet(
            context,
            'config.forms.ajaxAttribute',
            dataGet(context, 'config.forms.ajax.attribute', 'data-authkit-ajax')
        ),
        'data-authkit-ajax'
    );
}


/**
 * Determine whether a form is explicitly managed by the AJAX forms runtime.
 *
 * A form is treated as AJAX-managed when it exposes the configured AJAX marker
 * attribute, regardless of the attribute value.
 *
 * @param {HTMLFormElement|null} form
 * @param {Object|null} context
 * @returns {boolean}
 */
export function isAjaxManagedForm(form, context = null) {
    if (!isFormElement(form)) {
        return false;
    }

    const ajaxAttribute = getAjaxAttribute(context);

    return form.hasAttribute(ajaxAttribute);
}


/**
 * Resolve whether duplicate native submissions should be prevented.
 *
 * Resolution order:
 * - context.config.forms.loading.preventDoubleSubmit
 * - true fallback
 *
 * @param {Object|null} context
 * @returns {boolean}
 */
export function shouldPreventDoubleSubmit(context = null) {
    return Boolean(
        dataGet(context, 'config.forms.loading.preventDoubleSubmit', true)
    );
}


/**
 * Determine whether a form should receive native loading enhancement.
 *
 * Rules:
 * - must be a real form element
 * - must not be AJAX-managed
 *
 * @param {HTMLFormElement|null} form
 * @param {Object|null} context
 * @returns {boolean}
 */
export function shouldBindNativeLoading(form, context = null) {
    if (!isFormElement(form)) {
        return false;
    }

    return !isAjaxManagedForm(form, context);
}


/**
 * Determine whether a form is currently bound for native loading enhancement.
 *
 * @param {HTMLFormElement|null} form
 * @returns {boolean}
 */
export function isNativeLoadingBound(form) {
    if (!isFormElement(form)) {
        return false;
    }

    return nativeLoadingBindings.has(form);
}


/**
 * Resolve the stored native-loading binding entry for a form.
 *
 * @param {HTMLFormElement|null} form
 * @returns {{handler: Function, cleanup: Function, state: Object}|null}
 */
export function getNativeLoadingBinding(form) {
    if (!isFormElement(form)) {
        return null;
    }

    return nativeLoadingBindings.get(form) ?? null;
}


/**
 * Remove an active native-loading binding from a form.
 *
 * @param {HTMLFormElement|null} form
 * @returns {boolean}
 */
export function unbindNativeLoadingForm(form) {
    if (!isFormElement(form)) {
        return false;
    }

    const binding = getNativeLoadingBinding(form);

    if (!binding || !isFunction(binding.cleanup)) {
        return false;
    }

    binding.cleanup();

    return true;
}


/**
 * Bind native loading enhancement to a single HTTP form.
 *
 * Behavior:
 * - does not prevent default browser submission
 * - optionally ignores duplicate submits when already submitting
 * - applies loading state immediately before native submit proceeds
 * - marks the per-form state as submitting
 *
 * @param {HTMLFormElement|null} form
 * @param {Object|null} context
 * @param {Object} [options={}]
 * @returns {Function}
 */
export function bindNativeLoadingForm(form, context = null, options = {}) {
    if (!shouldBindNativeLoading(form, context)) {
        return () => {};
    }

    const existing = getNativeLoadingBinding(form);

    if (existing && isFunction(existing.cleanup)) {
        return existing.cleanup;
    }

    const formState = createFormState(form);
    const preventDoubleSubmit = options.preventDoubleSubmit !== false;

    const handler = () => {
        if (preventDoubleSubmit && isSubmitting(formState)) {
            return;
        }

        setMeta(formState, {
            native: true,
            outcome: null,
            loading: true,
        });

        setSubmitting(formState, true);
        applyLoadingState(form, context, options);
    };

    form.addEventListener('submit', handler);

    const cleanup = () => {
        form.removeEventListener('submit', handler);
        nativeLoadingBindings.delete(form);
    };

    nativeLoadingBindings.set(form, {
        handler,
        cleanup,
        state: formState,
    });

    return cleanup;
}


/**
 * Bind native loading enhancement to multiple forms.
 *
 * Returns a single cleanup callback that removes all successful bindings.
 *
 * @param {Array<*>} forms
 * @param {Object|null} context
 * @param {Object} [options={}]
 * @returns {Function}
 */
export function bindNativeLoadingForms(forms, context = null, options = {}) {
    if (!Array.isArray(forms)) {
        return () => {};
    }

    const cleanups = forms
        .filter((form) => shouldBindNativeLoading(form, context))
        .map((form) => bindNativeLoadingForm(form, context, options));

    return () => {
        cleanups.forEach((cleanup) => {
            if (isFunction(cleanup)) {
                cleanup();
            }
        });
    };
}


/**
 * Unbind native loading enhancement from multiple forms.
 *
 * @param {Array<*>} forms
 * @returns {number}
 */
export function unbindNativeLoadingForms(forms) {
    if (!Array.isArray(forms)) {
        return 0;
    }

    return forms.reduce((count, form) => {
        return unbindNativeLoadingForm(form) ? count + 1 : count;
    }, 0);
}


/**
 * Rebind native loading enhancement to a form.
 *
 * This first removes any existing binding, then applies a fresh binding.
 *
 * @param {HTMLFormElement|null} form
 * @param {Object|null} context
 * @param {Object} [options={}]
 * @returns {Function}
 */
export function rebindNativeLoadingForm(form, context = null, options = {}) {
    if (!isFormElement(form)) {
        return () => {};
    }

    unbindNativeLoadingForm(form);

    return bindNativeLoadingForm(form, context, options);
}


/**
 * Resolve all candidate forms for native loading enhancement.
 *
 * Current rule:
 * - use all forms from the current document scope
 *
 * @param {Object|null} context
 * @returns {HTMLFormElement[]}
 */
export function getNativeLoadingForms(context = null) {
    return getForms(document).filter((form) => shouldBindNativeLoading(form, context));
}


/**
 * Boot the native-submit loading enhancer.
 *
 * Responsibilities:
 * - resolve all non-AJAX forms in the current document
 * - bind native loading enhancement to each form
 * - return runtime metadata and cleanup handles
 *
 * @param {Object|null} context
 * @returns {{
 *   forms: HTMLFormElement[],
 *   count: number,
 *   cleanup: Function
 * }}
 */
export function bootNativeLoading(context = null) {
    const forms = getNativeLoadingForms(context);
    const preventDoubleSubmit = shouldPreventDoubleSubmit(context);

    const cleanup = bindNativeLoadingForms(forms, context, {
        preventDoubleSubmit,
    });

    return {
        forms,
        count: forms.length,
        cleanup,
    };
}