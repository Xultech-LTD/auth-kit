/**
 * AuthKit
 * -----------------------------------------------------------------------------
 * File: js/pages/two-factor-recovery.js
 * Author: Michael Erastus
 * Package: AuthKit
 *
 * Description:
 * -----------------------------------------------------------------------------
 * Two-factor recovery page runtime module for the AuthKit browser client.
 *
 * This file is responsible for discovering and exposing two-factor-recovery
 * page DOM references in a schema-safe way without assuming hard-coded field
 * names.
 *
 * Responsibilities:
 * - Confirm the current page matches the two-factor recovery runtime entry.
 * - Resolve the current page root element.
 * - Resolve the primary recovery form.
 * - Discover rendered controls from the current page form.
 * - Classify recovery controls without assuming fixed schema field names.
 * - Return a normalized page descriptor for tests and future enhancements.
 *
 * Design notes:
 * - This file does not own form submission. The shared forms module handles that.
 * - This file does not hard-code field names such as "challenge",
 *   "recovery_code", or "remember".
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
 * Resolve the primary two-factor recovery form from the current page.
 *
 * Current rule:
 * - first form within the current page element
 *
 * @param {Object|null} context
 * @returns {HTMLFormElement|null}
 */
export function getTwoFactorRecoveryForm(context) {
    const pageElement = context?.pageElement ?? context?.page?.element ?? null;
    const forms = getForms(pageElement || document);

    return forms[0] instanceof HTMLFormElement ? forms[0] : null;
}


/**
 * Resolve visible recovery controls without assuming a fixed field name.
 *
 * Strategy:
 * - visible controls only
 * - excludes password and checkbox controls
 *
 * @param {Element[]} controls
 * @returns {Array<HTMLInputElement|HTMLSelectElement|HTMLTextAreaElement>}
 */
export function getRecoveryControls(controls = []) {
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
 * Resolve the first visible recovery control from the rendered controls.
 *
 * @param {Element[]} controls
 * @returns {HTMLInputElement|HTMLSelectElement|HTMLTextAreaElement|null}
 */
export function getPrimaryRecoveryControl(controls = []) {
    const recoveryControls = getRecoveryControls(controls);

    return recoveryControls[0] ?? null;
}


/**
 * Resolve hidden context controls from the rendered controls collection.
 *
 * @param {Element[]} controls
 * @returns {HTMLInputElement[]}
 */
export function getContextControls(controls = []) {
    return controls.filter((control) => isHiddenControl(control));
}


/**
 * Resolve checkbox controls from the rendered controls collection.
 *
 * This is useful for optional controls such as "remember me" without assuming
 * the concrete field name.
 *
 * @param {Element[]} controls
 * @returns {HTMLInputElement[]}
 */
export function getRememberLikeControls(controls = []) {
    return controls.filter((control) => isCheckboxControl(control));
}


/**
 * Build a normalized two-factor recovery page descriptor.
 *
 * @param {Object|null} context
 * @returns {{
 *   page: HTMLElement|null,
 *   form: HTMLFormElement|null,
 *   controls: Element[],
 *   visibleControls: Element[],
 *   hiddenControls: HTMLInputElement[],
 *   checkboxControls: HTMLInputElement[],
 *   passwordControls: HTMLInputElement[],
 *   submitControls: Element[],
 *   links: HTMLAnchorElement[],
 *   recoveryControls: Array<HTMLInputElement|HTMLSelectElement|HTMLTextAreaElement>,
 *   primaryRecoveryControl: HTMLInputElement|HTMLSelectElement|HTMLTextAreaElement|null,
 *   contextControls: HTMLInputElement[],
 *   rememberLikeControls: HTMLInputElement[]
 * }}
 */
export function getTwoFactorRecoveryPageElements(context) {
    const page = context?.pageElement ?? context?.page?.element ?? null;
    const form = getTwoFactorRecoveryForm(context);
    const controls = getFormControls(form);

    return {
        page: page instanceof HTMLElement ? page : null,
        form,
        controls,
        visibleControls: controls.filter((control) => isVisibleFormControl(control)),
        hiddenControls: controls.filter((control) => isHiddenControl(control)),
        checkboxControls: controls.filter((control) => isCheckboxControl(control)),
        passwordControls: controls.filter((control) => isPasswordControl(control)),
        submitControls: controls.filter((control) => isSubmitControl(control)),
        links: page ? queryAll('a[href]', page).filter((link) => link instanceof HTMLAnchorElement) : [],
        recoveryControls: getRecoveryControls(controls),
        primaryRecoveryControl: getPrimaryRecoveryControl(controls),
        contextControls: getContextControls(controls),
        rememberLikeControls: getRememberLikeControls(controls),
    };
}


/**
 * Boot the two-factor recovery page runtime module.
 *
 * @param {Object} context
 * @returns {Object|null}
 */
export function boot(context) {
    if (!isObject(context) || !isCurrentPage('two_factor_recovery')) {
        return null;
    }

    const elements = getTwoFactorRecoveryPageElements(context);

    return {
        key: 'two_factor_recovery',
        ...elements,
    };
}