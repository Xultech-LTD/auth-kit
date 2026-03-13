/**
 * AuthKit
 * -----------------------------------------------------------------------------
 * File: js/pages/two-factor-challenge.js
 * Author: Michael Erastus
 * Package: AuthKit
 *
 * Description:
 * -----------------------------------------------------------------------------
 * Two-factor challenge page runtime module for the AuthKit browser client.
 *
 * This file is responsible for discovering and exposing two-factor-challenge
 * page DOM references in a schema-safe way without assuming hard-coded field
 * names.
 *
 * Responsibilities:
 * - Confirm the current page matches the two-factor challenge runtime entry.
 * - Resolve the current page root element.
 * - Resolve the primary challenge form.
 * - Discover rendered controls from the current page form.
 * - Classify verification controls without assuming fixed schema field names.
 * - Return a normalized page descriptor for tests and future enhancements.
 *
 * Design notes:
 * - This file does not own form submission. The shared forms module handles that.
 * - This file does not hard-code field names such as "challenge" or "code".
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
 * Determine whether a control looks OTP-like based on rendered attributes.
 *
 * Supported heuristics:
 * - autocomplete="one-time-code"
 * - inputmode="numeric"
 * - authkit-otp class hook
 *
 * @param {Element|null} element
 * @returns {boolean}
 */
export function isOtpLikeControl(element) {
    if (!isVisibleFormControl(element)) {
        return false;
    }

    const autocomplete = String(getAttribute(element, 'autocomplete', '') || '').toLowerCase();
    const inputmode = String(getAttribute(element, 'inputmode', '') || '').toLowerCase();
    const className = element instanceof Element ? element.className : '';

    return (
        autocomplete === 'one-time-code' ||
        inputmode === 'numeric' ||
        String(className).includes('authkit-otp')
    );
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
 * Resolve the primary two-factor challenge form from the current page.
 *
 * Current rule:
 * - first form within the current page element
 *
 * @param {Object|null} context
 * @returns {HTMLFormElement|null}
 */
export function getTwoFactorChallengeForm(context) {
    const pageElement = context?.pageElement ?? context?.page?.element ?? null;
    const forms = getForms(pageElement || document);

    return forms[0] instanceof HTMLFormElement ? forms[0] : null;
}


/**
 * Resolve visible verification controls without assuming a fixed field name.
 *
 * Strategy:
 * - visible controls only
 * - excludes password and checkbox controls
 *
 * @param {Element[]} controls
 * @returns {Array<HTMLInputElement|HTMLSelectElement|HTMLTextAreaElement>}
 */
export function getVerificationControls(controls = []) {
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
 * Resolve OTP-like controls from the rendered controls collection.
 *
 * @param {Element[]} controls
 * @returns {Array<HTMLInputElement|HTMLSelectElement|HTMLTextAreaElement>}
 */
export function getOtpLikeControls(controls = []) {
    return controls.filter((control) => isOtpLikeControl(control));
}


/**
 * Resolve the first visible verification control from the rendered controls.
 *
 * @param {Element[]} controls
 * @returns {HTMLInputElement|HTMLSelectElement|HTMLTextAreaElement|null}
 */
export function getPrimaryVerificationControl(controls = []) {
    const verificationControls = getVerificationControls(controls);

    return verificationControls[0] ?? null;
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
 * Build a normalized two-factor challenge page descriptor.
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
 *   verificationControls: Array<HTMLInputElement|HTMLSelectElement|HTMLTextAreaElement>,
 *   otpLikeControls: Array<HTMLInputElement|HTMLSelectElement|HTMLTextAreaElement>,
 *   primaryVerificationControl: HTMLInputElement|HTMLSelectElement|HTMLTextAreaElement|null,
 *   contextControls: HTMLInputElement[]
 * }}
 */
export function getTwoFactorChallengePageElements(context) {
    const page = context?.pageElement ?? context?.page?.element ?? null;
    const form = getTwoFactorChallengeForm(context);
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
        verificationControls: getVerificationControls(controls),
        otpLikeControls: getOtpLikeControls(controls),
        primaryVerificationControl: getPrimaryVerificationControl(controls),
        contextControls: getContextControls(controls),
    };
}


/**
 * Boot the two-factor challenge page runtime module.
 *
 * @param {Object} context
 * @returns {Object|null}
 */
export function boot(context) {
    if (!isObject(context) || !isCurrentPage('two_factor_challenge')) {
        return null;
    }

    const elements = getTwoFactorChallengePageElements(context);

    return {
        key: 'two_factor_challenge',
        ...elements,
    };
}