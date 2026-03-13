/**
 * AuthKit
 * -----------------------------------------------------------------------------
 * File: js/modules/forms/bind-forms.js
 * Author: Michael Erastus
 * Package: AuthKit
 *
 * Description:
 * -----------------------------------------------------------------------------
 * Form binding utilities for the AuthKit AJAX forms module.
 *
 * This file is responsible for attaching submit behavior to AuthKit-managed
 * forms while keeping listener registration and cleanup predictable.
 *
 * Responsibilities:
 * - Bind a submit handler to a single form.
 * - Prevent duplicate binding of the same form.
 * - Expose cleanup callbacks for unbinding.
 * - Track per-form binding state safely.
 * - Provide bulk binding helpers for multiple forms.
 * - Provide the runtime boot entry for the shared forms module.
 *
 * Design notes:
 * - This file does not render UI state or feedback directly.
 * - This file coordinates DOM event binding for form submission.
 * - Runtime boot remains lightweight and delegates submission work to submit.js.
 *
 * Binding contract:
 * - A bound form receives exactly one active submit listener per binder instance.
 * - Rebinding an already bound form returns the existing cleanup callback.
 * - Cleanup removes the listener and clears internal binding state.
 *
 * @package   AuthKit
 * @author    Michael Erastus
 * @license   MIT
 */

import { getAjaxForms } from '../../core/dom.js';
import { isFunction } from '../../core/helpers.js';
import { createFormState } from './state.js';
import { submitForm } from './submit.js';


/**
 * Internal registry of active form bindings.
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
const formBindings = new WeakMap();


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
 * Determine whether a form is currently bound.
 *
 * @param {HTMLFormElement|null} form
 * @returns {boolean}
 */
export function isFormBound(form) {
    if (!isFormElement(form)) {
        return false;
    }

    return formBindings.has(form);
}


/**
 * Resolve the stored binding entry for a form.
 *
 * @param {HTMLFormElement|null} form
 * @returns {{handler: Function, cleanup: Function, state: Object}|null}
 */
export function getFormBinding(form) {
    if (!isFormElement(form)) {
        return null;
    }

    return formBindings.get(form) ?? null;
}


/**
 * Remove an active binding from a form.
 *
 * @param {HTMLFormElement|null} form
 * @returns {boolean}
 */
export function unbindForm(form) {
    if (!isFormElement(form)) {
        return false;
    }

    const binding = getFormBinding(form);

    if (!binding || !isFunction(binding.cleanup)) {
        return false;
    }

    binding.cleanup();

    return true;
}


/**
 * Bind a submit handler to a single form.
 *
 * The submit handler receives:
 * - the original submit event
 * - the bound form element
 * - the per-form runtime state
 *
 * The default browser submit is prevented automatically.
 *
 * @param {HTMLFormElement|null} form
 * @param {(event: SubmitEvent, form: HTMLFormElement, formState: Object) => void} submitHandler
 * @returns {Function}
 */
export function bindForm(form, submitHandler) {
    if (!isFormElement(form) || !isFunction(submitHandler)) {
        return () => {};
    }

    const existing = getFormBinding(form);

    if (existing && isFunction(existing.cleanup)) {
        return existing.cleanup;
    }

    const formState = createFormState(form);

    const handler = (event) => {
        event.preventDefault();

        submitHandler(event, form, formState);
    };

    form.addEventListener('submit', handler);

    const cleanup = () => {
        form.removeEventListener('submit', handler);
        formBindings.delete(form);
    };

    formBindings.set(form, {
        handler,
        cleanup,
        state: formState,
    });

    return cleanup;
}


/**
 * Bind multiple forms with the same submit handler.
 *
 * Returns a single cleanup callback that removes all successful bindings.
 *
 * @param {Array<*>} forms
 * @param {(event: SubmitEvent, form: HTMLFormElement, formState: Object) => void} submitHandler
 * @returns {Function}
 */
export function bindForms(forms, submitHandler) {
    if (!Array.isArray(forms) || !isFunction(submitHandler)) {
        return () => {};
    }

    const cleanups = forms
        .filter((form) => isFormElement(form))
        .map((form) => bindForm(form, submitHandler));

    return () => {
        cleanups.forEach((cleanup) => {
            if (isFunction(cleanup)) {
                cleanup();
            }
        });
    };
}


/**
 * Unbind multiple forms.
 *
 * @param {Array<*>} forms
 * @returns {number}
 */
export function unbindForms(forms) {
    if (!Array.isArray(forms)) {
        return 0;
    }

    return forms.reduce((count, form) => {
        return unbindForm(form) ? count + 1 : count;
    }, 0);
}


/**
 * Rebind a form with a fresh handler.
 *
 * This first removes any existing binding, then binds the new handler.
 *
 * @param {HTMLFormElement|null} form
 * @param {(event: SubmitEvent, form: HTMLFormElement, formState: Object) => void} submitHandler
 * @returns {Function}
 */
export function rebindForm(form, submitHandler) {
    if (!isFormElement(form) || !isFunction(submitHandler)) {
        return () => {};
    }

    unbindForm(form);

    return bindForm(form, submitHandler);
}


/**
 * Boot the AuthKit AJAX forms runtime module.
 *
 * Responsibilities:
 * - Resolve all AJAX-enabled AuthKit forms from the DOM.
 * - Bind each form to the shared submit pipeline.
 * - Return runtime metadata and cleanup handles for diagnostics or teardown.
 *
 * @param {Object} context
 * @returns {{
 *   forms: HTMLFormElement[],
 *   count: number,
 *   cleanup: Function
 * }}
 */
export function bootForms(context) {
    const ajaxAttribute = String(
        context?.config?.forms?.ajaxAttribute
        ?? context?.config?.forms?.ajax?.attribute
        ?? 'data-authkit-ajax'
    );

    const forms = getAjaxForms(ajaxAttribute);

    const cleanup = bindForms(forms, (event, form, formState) => {
        void submitForm(context, form, formState, {
            event,
        });
    });

    return {
        forms,
        count: forms.length,
        cleanup,
    };
}