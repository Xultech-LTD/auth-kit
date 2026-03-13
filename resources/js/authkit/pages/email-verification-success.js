/**
 * AuthKit
 * -----------------------------------------------------------------------------
 * File: js/pages/email-verification-success.js
 * Author: Michael Erastus
 * Package: AuthKit
 *
 * Description:
 * -----------------------------------------------------------------------------
 * Email verification success page runtime module for the AuthKit browser client.
 *
 * This file is responsible for discovering and exposing email-verification-
 * success page DOM references in a safe, page-oriented way.
 *
 * Responsibilities:
 * - Confirm the current page matches the email verification success runtime entry.
 * - Resolve the current page root element.
 * - Discover navigation links rendered on the page.
 * - Discover whether the page contains any forms.
 * - Return a normalized page descriptor for tests and future enhancements.
 *
 * Design notes:
 * - This page is informational and typically does not contain a form.
 * - This file does not own submission or redirect behavior.
 * - This file relies on rendered DOM output rather than raw PHP config.
 * - This file remains progressively enhanced and safe when JavaScript is absent.
 *
 * @package   AuthKit
 * @author    Michael Erastus
 * @license   MIT
 */

import { getForms, queryAll } from '../core/dom.js';
import { isCurrentPage } from '../core/page.js';
import { isObject } from '../core/helpers.js';


/**
 * Resolve all links rendered within the current page.
 *
 * @param {HTMLElement|null} page
 * @returns {HTMLAnchorElement[]}
 */
export function getSuccessPageLinks(page) {
    if (!(page instanceof HTMLElement)) {
        return [];
    }

    return queryAll('a[href]', page).filter((link) => link instanceof HTMLAnchorElement);
}


/**
 * Resolve all forms rendered within the current page.
 *
 * This page usually has no forms, but the helper remains available for safety
 * and future extensibility.
 *
 * @param {Object|null} context
 * @returns {HTMLFormElement[]}
 */
export function getSuccessPageForms(context) {
    const pageElement = context?.pageElement ?? context?.page?.element ?? null;
    const forms = getForms(pageElement || document);

    return forms.filter((form) => form instanceof HTMLFormElement);
}


/**
 * Resolve the primary call-to-action link for the success page.
 *
 * Current rule:
 * - first rendered anchor within the current page
 *
 * @param {HTMLElement|null} page
 * @returns {HTMLAnchorElement|null}
 */
export function getPrimaryActionLink(page) {
    const links = getSuccessPageLinks(page);

    return links[0] ?? null;
}


/**
 * Build a normalized email verification success page descriptor.
 *
 * @param {Object|null} context
 * @returns {{
 *   page: HTMLElement|null,
 *   forms: HTMLFormElement[],
 *   formCount: number,
 *   links: HTMLAnchorElement[],
 *   primaryActionLink: HTMLAnchorElement|null
 * }}
 */
export function getEmailVerificationSuccessPageElements(context) {
    const page = context?.pageElement ?? context?.page?.element ?? null;
    const forms = getSuccessPageForms(context);
    const links = getSuccessPageLinks(page instanceof HTMLElement ? page : null);

    return {
        page: page instanceof HTMLElement ? page : null,
        forms,
        formCount: forms.length,
        links,
        primaryActionLink: getPrimaryActionLink(page instanceof HTMLElement ? page : null),
    };
}


/**
 * Boot the email verification success page runtime module.
 *
 * @param {Object} context
 * @returns {Object|null}
 */
export function boot(context) {
    if (!isObject(context) || !isCurrentPage('email_verification_success')) {
        return null;
    }

    const elements = getEmailVerificationSuccessPageElements(context);

    return {
        key: 'email_verification_success',
        ...elements,
    };
}