/**
 * AuthKit
 * -----------------------------------------------------------------------------
 * File: js/pages/register.js
 * Author: Michael Erastus
 * Package: AuthKit
 *
 * Description:
 * -----------------------------------------------------------------------------
 * Register page runtime module for the AuthKit browser client.
 *
 * This file is responsible for discovering and exposing register-page-specific
 * DOM references in a schema-safe way without assuming hard-coded field names.
 *
 * Responsibilities:
 * - Confirm the current page matches the register page runtime entry.
 * - Resolve the register page root element.
 * - Resolve the primary register form.
 * - Discover rendered controls from the current page form.
 * - Classify important controls without assuming fixed schema field names.
 * - Return a normalized page descriptor for tests and future enhancements.
 *
 * Design notes:
 * - This file does not own form submission. The shared forms module handles that.
 * - This file does not hard-code identity, password, or confirmation field names.
 * - This file relies on rendered DOM output rather than raw PHP schema config.
 * - This file remains progressively enhanced and safe when JavaScript is absent.
 *
 * @package   AuthKit
 * @author    Michael Erastus
 * @license   MIT
 */

import { getForms, getAttribute, queryAll } from '../core/dom.js';
import { isCurrentPage } from '../core/page.js';
import { isObject } from '../core/helpers.js';


/**
 * Determine whether a control is a hidden input.
 *
 * @param {Element|null} element
 * @returns {boolean}
 */
export function isHiddenControl(element) {
    return Boolean(
        element instanceof HTMLInputElement &&
        String(element.type).toLowerCase() === 'hidden'
    );
}


/**
 * Determine whether a control is a checkbox input.
 *
 * @param {Element|null} element
 * @returns {boolean}
 */
export function isCheckboxControl(element) {
    return Boolean(
        element instanceof HTMLInputElement &&
        String(element.type).toLowerCase() === 'checkbox'
    );
}


/**
 * Determine whether a control is a password input.
 *
 * @param {Element|null} element
 * @returns {boolean}
 */
export function isPasswordControl(element) {
    return Boolean(
        element instanceof HTMLInputElement &&
        String(element.type).toLowerCase() === 'password'
    );
}


/**
 * Determine whether a control is a submit-capable button/input.
 *
 * @param {Element|null} element
 * @returns {boolean}
 */
export function isSubmitControl(element) {
    if (element instanceof HTMLButtonElement) {
        return (getAttribute(element, 'type', 'submit') || 'submit').toLowerCase() === 'submit';
    }

    if (element instanceof HTMLInputElement) {
        const type = String(element.type || '').toLowerCase();

        return type === 'submit' || type === 'image';
    }

    return false;
}


/**
 * Determine whether a control is a visible user-facing form control.
 *
 * Rules:
 * - excludes hidden inputs
 * - includes input, select, and textarea controls
 *
 * @param {Element|null} element
 * @returns {boolean}
 */
export function isVisibleFormControl(element) {
    if (
        !(element instanceof HTMLInputElement) &&
        !(element instanceof HTMLSelectElement) &&
        !(element instanceof HTMLTextAreaElement)
    ) {
        return false;
    }

    return !isHiddenControl(element);
}


/**
 * Resolve all form controls from a form.
 *
 * @param {HTMLFormElement|null} form
 * @returns {Element[]}
 */
export function getFormControls(form) {
    if (!(form instanceof HTMLFormElement)) {
        return [];
    }

    return queryAll('input, select, textarea, button', form);
}


/**
 * Resolve the primary register form from the current page.
 *
 * Current rule:
 * - first form within the current page element
 *
 * @param {Object|null} context
 * @returns {HTMLFormElement|null}
 */
export function getRegisterForm(context) {
    const pageElement = context?.pageElement ?? context?.page?.element ?? null;
    const forms = getForms(pageElement || document);

    return forms[0] instanceof HTMLFormElement ? forms[0] : null;
}


/**
 * Resolve the most likely identity-like visible controls.
 *
 * Strategy:
 * - visible controls only
 * - excludes password and checkbox controls
 *
 * This keeps the register page schema-safe for consumers that replace or reorder
 * fields such as name, email, username, phone, or custom profile inputs.
 *
 * @param {Element[]} controls
 * @returns {Array<HTMLInputElement|HTMLSelectElement|HTMLTextAreaElement>}
 */
export function getIdentityLikeControls(controls = []) {
    return controls.filter((control) => {
        if (!isVisibleFormControl(control)) {
            return false;
        }

        if (isPasswordControl(control) || isCheckboxControl(control)) {
            return false;
        }

        return true;
    });
}


/**
 * Resolve password controls from the rendered controls collection.
 *
 * This helper is intentionally name-agnostic and relies only on input type.
 *
 * @param {Element[]} controls
 * @returns {HTMLInputElement[]}
 */
export function getPasswordControls(controls = []) {
    return controls.filter((control) => isPasswordControl(control));
}


/**
 * Resolve the first visible identity-like control from the rendered controls.
 *
 * @param {Element[]} controls
 * @returns {HTMLInputElement|HTMLSelectElement|HTMLTextAreaElement|null}
 */
export function getPrimaryIdentityControl(controls = []) {
    const visibleIdentityLikeControls = getIdentityLikeControls(controls);

    return visibleIdentityLikeControls[0] ?? null;
}


/**
 * Resolve the primary password control from the rendered controls.
 *
 * @param {Element[]} controls
 * @returns {HTMLInputElement|null}
 */
export function getPrimaryPasswordControl(controls = []) {
    const passwordControls = getPasswordControls(controls);

    return passwordControls[0] ?? null;
}


/**
 * Resolve the password confirmation control from the rendered controls.
 *
 * Strategy:
 * - second password-like control in the rendered form, when present
 *
 * This remains schema-safe because it does not assume a fixed field name.
 *
 * @param {Element[]} controls
 * @returns {HTMLInputElement|null}
 */
export function getPasswordConfirmationControl(controls = []) {
    const passwordControls = getPasswordControls(controls);

    return passwordControls[1] ?? null;
}


/**
 * Build a normalized register page descriptor.
 *
 * @param {Object|null} context
 * @returns {{
 *   page: HTMLElement|null,
 *   form: HTMLFormElement|null,
 *   controls: Element[],
 *   visibleControls: Element[],
 *   hiddenControls: Element[],
 *   checkboxControls: HTMLInputElement[],
 *   passwordControls: HTMLInputElement[],
 *   submitControls: Element[],
 *   links: HTMLAnchorElement[],
 *   identityLikeControls: Array<HTMLInputElement|HTMLSelectElement|HTMLTextAreaElement>,
 *   primaryIdentityControl: HTMLInputElement|HTMLSelectElement|HTMLTextAreaElement|null,
 *   primaryPasswordControl: HTMLInputElement|null,
 *   passwordConfirmationControl: HTMLInputElement|null
 * }}
 */
export function getRegisterPageElements(context) {
    const page = context?.pageElement ?? context?.page?.element ?? null;
    const form = getRegisterForm(context);
    const controls = getFormControls(form);

    return {
        page: page instanceof HTMLElement ? page : null,
        form,
        controls,
        visibleControls: controls.filter((control) => isVisibleFormControl(control)),
        hiddenControls: controls.filter((control) => isHiddenControl(control)),
        checkboxControls: controls.filter((control) => isCheckboxControl(control)),
        passwordControls: getPasswordControls(controls),
        submitControls: controls.filter((control) => isSubmitControl(control)),
        links: page ? queryAll('a[href]', page).filter((link) => link instanceof HTMLAnchorElement) : [],
        identityLikeControls: getIdentityLikeControls(controls),
        primaryIdentityControl: getPrimaryIdentityControl(controls),
        primaryPasswordControl: getPrimaryPasswordControl(controls),
        passwordConfirmationControl: getPasswordConfirmationControl(controls),
    };
}


/**
 * Boot the register page runtime module.
 *
 * @param {Object} context
 * @returns {Object|null}
 */
export function boot(context) {
    if (!isObject(context) || !isCurrentPage('register')) {
        return null;
    }

    const elements = getRegisterPageElements(context);

    return {
        key: 'register',
        ...elements,
    };
}