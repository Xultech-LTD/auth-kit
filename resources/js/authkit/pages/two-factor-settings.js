/**
 * AuthKit
 * -----------------------------------------------------------------------------
 * File: js/pages/two-factor-settings.js
 * Author: Michael Erastus
 * Package: AuthKit
 *
 * Description:
 * -----------------------------------------------------------------------------
 * Two-factor settings page runtime module for the AuthKit browser client.
 *
 * This file is responsible for progressively enhancing the authenticated
 * two-factor settings page.
 *
 * Responsibilities:
 * - Confirm the current page matches the two-factor settings runtime entry.
 * - Resolve key two-factor settings page DOM elements.
 * - Toggle between the default disable form and the recovery-code disable form.
 * - Hide or reveal the default disable explanatory note based on the active mode.
 * - Hydrate newly generated recovery codes after successful AJAX submissions.
 * - Reveal the recovery-code display section only when codes are available.
 * - Hide the recovery-code display section when no codes are available.
 * - Provide a download action for newly generated recovery codes.
 *
 * Design notes:
 * - This file does not own HTTP submission. The shared forms module handles that.
 * - This file does not hard-code server response structure beyond the configured
 *   recovery response key supplied by the Blade page.
 * - This file remains progressively enhanced and safe when JavaScript is absent.
 *
 * Expected DOM hooks:
 * - [data-authkit-two-factor-settings]
 * - [data-authkit-two-factor-recovery]
 * - [data-authkit-two-factor-recovery-list]
 * - [data-authkit-two-factor-download]
 * - [data-authkit-two-factor-disable-toggle]
 * - [data-authkit-two-factor-disable-note]
 * - [data-authkit-two-factor-disable-recovery]
 * - [data-authkit-two-factor-disable-form]
 *
 * @package   AuthKit
 * @author    Michael Erastus
 * @license   MIT
 */

import { getAttribute, listen, queryAll, queryOne } from '../core/dom.js';
import { isCurrentPage } from '../core/page.js';
import { dataGet, isObject, isString, normalizeString } from '../core/helpers.js';
import { onFormSuccess } from '../core/events.js';


/**
 * Resolve the two-factor settings page root element.
 *
 * @param {Object|null} context
 * @returns {HTMLElement|null}
 */
export function getTwoFactorSettingsRoot(context) {
    const page = context?.pageElement ?? context?.page?.element ?? null;

    if (!(page instanceof HTMLElement)) {
        return null;
    }

    return queryOne('[data-authkit-two-factor-settings]', page) ?? page;
}


/**
 * Resolve the recovery-code presentation section.
 *
 * @param {HTMLElement|null} root
 * @returns {HTMLElement|null}
 */
export function getRecoverySection(root) {
    if (!(root instanceof HTMLElement)) {
        return null;
    }

    const section = queryOne('[data-authkit-two-factor-recovery]', root);

    return section instanceof HTMLElement ? section : null;
}


/**
 * Resolve the recovery-code list container.
 *
 * @param {HTMLElement|null} root
 * @returns {HTMLElement|null}
 */
export function getRecoveryList(root) {
    if (!(root instanceof HTMLElement)) {
        return null;
    }

    const list = queryOne('[data-authkit-two-factor-recovery-list]', root);

    return list instanceof HTMLElement ? list : null;
}


/**
 * Resolve the recovery-code download button.
 *
 * @param {HTMLElement|null} root
 * @returns {HTMLButtonElement|null}
 */
export function getRecoveryDownloadButton(root) {
    if (!(root instanceof HTMLElement)) {
        return null;
    }

    const button = queryOne('[data-authkit-two-factor-download]', root);

    return button instanceof HTMLButtonElement ? button : null;
}


/**
 * Resolve the default disable explanatory note block.
 *
 * @param {HTMLElement|null} root
 * @returns {HTMLElement|null}
 */
export function getDisableNote(root) {
    if (!(root instanceof HTMLElement)) {
        return null;
    }

    const note = queryOne('[data-authkit-two-factor-disable-note]', root);

    return note instanceof HTMLElement ? note : null;
}


/**
 * Resolve all disable-form toggle controls.
 *
 * @param {HTMLElement|null} root
 * @returns {HTMLButtonElement[]}
 */
export function getDisableToggleButtons(root) {
    if (!(root instanceof HTMLElement)) {
        return [];
    }

    return queryAll('[data-authkit-two-factor-disable-toggle]', root).filter(
        (element) => element instanceof HTMLButtonElement
    );
}


/**
 * Resolve the default authenticator-code disable form.
 *
 * @param {HTMLElement|null} root
 * @returns {HTMLFormElement|null}
 */
export function getDisableCodeForm(root) {
    if (!(root instanceof HTMLElement)) {
        return null;
    }

    const form = queryOne(
        '[data-authkit-two-factor-disable-form][data-authkit-two-factor-disable-mode="code"]',
        root
    );

    return form instanceof HTMLFormElement ? form : null;
}


/**
 * Resolve the recovery-code disable form wrapper.
 *
 * The wrapper is hidden/revealed as a block rather than only toggling the form.
 *
 * @param {HTMLElement|null} root
 * @returns {HTMLElement|null}
 */
export function getDisableRecoveryWrapper(root) {
    if (!(root instanceof HTMLElement)) {
        return null;
    }

    const wrapper = queryOne('[data-authkit-two-factor-disable-recovery]', root);

    return wrapper instanceof HTMLElement ? wrapper : null;
}


/**
 * Resolve the recovery-code disable form.
 *
 * @param {HTMLElement|null} root
 * @returns {HTMLFormElement|null}
 */
export function getDisableRecoveryForm(root) {
    if (!(root instanceof HTMLElement)) {
        return null;
    }

    const form = queryOne(
        '[data-authkit-two-factor-disable-form][data-authkit-two-factor-disable-mode="recovery"]',
        root
    );

    return form instanceof HTMLFormElement ? form : null;
}


/**
 * Resolve the configured AJAX recovery response key from the recovery section.
 *
 * Blade should supply this through a data attribute so page JavaScript does not
 * hard-code package config values.
 *
 * @param {HTMLElement|null} recoverySection
 * @returns {string}
 */
export function getRecoveryResponseKey(recoverySection) {
    const responseKey = normalizeString(
        getAttribute(recoverySection, 'data-authkit-two-factor-recovery-response-key', ''),
        ''
    );

    return responseKey !== '' ? responseKey : 'recovery_codes';
}


/**
 * Normalize a potential recovery-code payload into a clean string array.
 *
 * @param {*} value
 * @returns {string[]}
 */
export function normalizeRecoveryCodes(value) {
    if (!Array.isArray(value)) {
        return [];
    }

    return value
        .map((item) => (isString(item) ? item.trim() : ''))
        .filter((item) => item !== '');
}


/**
 * Resolve the currently rendered recovery codes from the DOM.
 *
 * @param {HTMLElement|null} root
 * @returns {string[]}
 */
export function getRenderedRecoveryCodes(root) {
    const list = getRecoveryList(root);

    if (!(list instanceof HTMLElement)) {
        return [];
    }

    return String(list.textContent ?? '')
        .split('\n')
        .map((line) => line.trim())
        .filter((line) => line !== '');
}


/**
 * Hide the recovery-code section and clear its rendered content.
 *
 * @param {HTMLElement|null} root
 * @returns {boolean}
 */
export function clearRecoveryCodes(root) {
    const wrapper = getRecoverySectionWrapper(root);
    const section = getRecoverySection(root);
    const list = getRecoveryList(root);

    if (!(section instanceof HTMLElement) || !(list instanceof HTMLElement)) {
        return false;
    }

    list.textContent = '';
    section.hidden = true;

    if (wrapper instanceof HTMLElement) {
        wrapper.hidden = true;
    }

    return true;
}


/**
 * Sync the recovery-code section visibility with its rendered content.
 *
 * @param {HTMLElement|null} root
 * @returns {boolean}
 */
export function syncRecoverySectionVisibility(root) {
    const wrapper = getRecoverySectionWrapper(root);
    const section = getRecoverySection(root);
    const codes = getRenderedRecoveryCodes(root);
    const hasCodes = codes.length > 0;

    if (section instanceof HTMLElement) {
        section.hidden = !hasCodes;
    }

    if (wrapper instanceof HTMLElement) {
        wrapper.hidden = !hasCodes;
    }

    return true;
}


/**
 * Show the authenticator-code disable form and hide the recovery fallback form.
 *
 * Also restores the default disable explanatory note.
 *
 * @param {HTMLElement|null} root
 * @returns {void}
 */
export function showDisableCodeMode(root) {
    const codeForm = getDisableCodeForm(root);
    const recoveryWrapper = getDisableRecoveryWrapper(root);
    const note = getDisableNote(root);

    if (codeForm instanceof HTMLFormElement) {
        codeForm.hidden = false;
    }

    if (recoveryWrapper instanceof HTMLElement) {
        recoveryWrapper.hidden = true;
    }

    if (note instanceof HTMLElement) {
        note.hidden = false;
    }
}


/**
 * Show the recovery-code disable form and hide the default authenticator-code form.
 *
 * Also hides the default disable explanatory note because recovery mode renders
 * its own separate explanatory content.
 *
 * @param {HTMLElement|null} root
 * @returns {void}
 */
export function showDisableRecoveryMode(root) {
    const codeForm = getDisableCodeForm(root);
    const recoveryWrapper = getDisableRecoveryWrapper(root);
    const note = getDisableNote(root);

    if (codeForm instanceof HTMLFormElement) {
        codeForm.hidden = true;
    }

    if (recoveryWrapper instanceof HTMLElement) {
        recoveryWrapper.hidden = false;
    }

    if (note instanceof HTMLElement) {
        note.hidden = true;
    }
}


/**
 * Apply a disable-form target mode from a toggle control.
 *
 * Supported targets:
 * - code
 * - recovery
 *
 * @param {HTMLElement|null} root
 * @param {string} target
 * @returns {void}
 */
export function applyDisableMode(root, target) {
    const normalizedTarget = normalizeString(target, '');

    if (normalizedTarget === 'recovery') {
        showDisableRecoveryMode(root);
        return;
    }

    showDisableCodeMode(root);
}


/**
 * Render recovery codes into the page and reveal the recovery-code section.
 *
 * When the provided recovery code list is empty, the section is cleared and hidden.
 *
 * @param {HTMLElement|null} root
 * @param {string[]} recoveryCodes
 * @returns {boolean}
 */
export function renderRecoveryCodes(root, recoveryCodes = []) {
    const wrapper = getRecoverySectionWrapper(root);
    const section = getRecoverySection(root);
    const list = getRecoveryList(root);
    const normalizedCodes = normalizeRecoveryCodes(recoveryCodes);

    if (!(section instanceof HTMLElement) || !(list instanceof HTMLElement)) {
        return false;
    }

    if (normalizedCodes.length === 0) {
        return clearRecoveryCodes(root);
    }

    list.textContent = normalizedCodes.join('\n');
    section.hidden = false;

    if (wrapper instanceof HTMLElement) {
        wrapper.hidden = false;
    }

    return true;
}


/**
 * Build a download filename for recovery codes.
 *
 * @returns {string}
 */
export function getRecoveryDownloadFilename() {
    const date = new Date();
    const year = String(date.getFullYear());
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `authkit-recovery-codes-${year}-${month}-${day}.txt`;
}


/**
 * Trigger a browser download for the currently rendered recovery codes.
 *
 * @param {HTMLElement|null} root
 * @param {Event|null} [event=null]
 * @returns {boolean}
 */
export function downloadRecoveryCodes(root, event = null) {
    if (event && typeof event.preventDefault === 'function') {
        event.preventDefault();
    }

    const codes = getRenderedRecoveryCodes(root);

    if (codes.length === 0) {
        return false;
    }

    const content = codes.join('\n');
    const blob = new Blob([content + '\n'], {
        type: 'text/plain;charset=utf-8',
    });

    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');

    link.href = url;
    link.download = getRecoveryDownloadFilename();
    link.setAttribute('aria-hidden', 'true');
    link.tabIndex = -1;
    link.style.position = 'fixed';
    link.style.left = '-9999px';

    document.body.appendChild(link);
    link.click();

    window.setTimeout(() => {
        window.URL.revokeObjectURL(url);
        link.remove();
    }, 300);

    return true;
}


/**
 * Resolve recovery codes from a normalized AuthKit form-success event payload.
 *
 * Supported source:
 * - detail.result.<configured response key>
 *
 * @param {CustomEvent|Event|null} event
 * @param {string} responseKey
 * @returns {string[]}
 */
export function resolveRecoveryCodesFromSuccessEvent(event, responseKey) {
    const result = dataGet(event, 'detail.result', null);

    if (!isObject(result)) {
        return [];
    }

    return normalizeRecoveryCodes(result[responseKey] ?? null);
}


/**
 * Determine whether a successful form event likely relates to two-factor actions
 * that may return recovery codes.
 *
 * Current safe rule:
 * - if recovery codes exist under the configured response key, treat it as relevant
 *
 * @param {CustomEvent|Event|null} event
 * @param {string} responseKey
 * @returns {boolean}
 */
export function isRecoveryEvent(event, responseKey) {
    return resolveRecoveryCodesFromSuccessEvent(event, responseKey).length > 0;
}


/**
 * Bind click behavior for disable-form toggle controls.
 *
 * @param {HTMLElement|null} root
 * @returns {Function}
 */
export function bindDisableToggleButtons(root) {
    const buttons = getDisableToggleButtons(root);

    const cleanups = buttons.map((button) => {
        return listen(button, 'click', () => {
            const target = normalizeString(
                button.getAttribute('data-authkit-two-factor-disable-target'),
                'code'
            );

            applyDisableMode(root, target);
        });
    });

    return () => {
        cleanups.forEach((cleanup) => cleanup());
    };
}


/**
 * Bind click behavior for the recovery-code download action.
 *
 * @param {HTMLElement|null} root
 * @returns {Function}
 */
export function bindRecoveryDownload(root) {
    const button = getRecoveryDownloadButton(root);

    if (!(button instanceof HTMLElement)) {
        return () => {};
    }

    return listen(button, 'click', (event) => {
        downloadRecoveryCodes(root, event);
    });
}


/**
 * Bind AJAX form-success hydration for newly generated recovery codes.
 *
 * @param {HTMLElement|null} root
 * @returns {Function}
 */
export function bindRecoverySuccessHydration(root) {
    const recoverySection = getRecoverySection(root);
    const responseKey = getRecoveryResponseKey(recoverySection);

    return onFormSuccess((event) => {
        if (!isRecoveryEvent(event, responseKey)) {
            return;
        }

        const recoveryCodes = resolveRecoveryCodesFromSuccessEvent(event, responseKey);

        renderRecoveryCodes(root, recoveryCodes);
    });
}


/**
 * Resolve a normalized two-factor settings page descriptor.
 *
 * @param {Object|null} context
 * @returns {{
 *   root: HTMLElement|null,
 *   recoverySection: HTMLElement|null,
 *   recoveryList: HTMLElement|null,
 *   recoveryDownloadButton: HTMLButtonElement|null,
 *   disableNote: HTMLElement|null,
 *   disableCodeForm: HTMLFormElement|null,
 *   disableRecoveryWrapper: HTMLElement|null,
 *   disableRecoveryForm: HTMLFormElement|null,
 *   recoverySectionWrapper: HTMLFormElement|null,
 *   disableToggleButtons: HTMLButtonElement[]
 * }}
 */
export function getTwoFactorSettingsPageElements(context) {
    const root = getTwoFactorSettingsRoot(context);

    return {
        root,
        recoverySectionWrapper: getRecoverySectionWrapper(root),
        recoverySection: getRecoverySection(root),
        recoveryList: getRecoveryList(root),
        recoveryDownloadButton: getRecoveryDownloadButton(root),
        disableNote: getDisableNote(root),
        disableCodeForm: getDisableCodeForm(root),
        disableRecoveryWrapper: getDisableRecoveryWrapper(root),
        disableRecoveryForm: getDisableRecoveryForm(root),
        disableToggleButtons: getDisableToggleButtons(root),
    };
}

/**
 * Resolve the outer recovery-code section wrapper.
 *
 * This wrapper contains the section heading/description and must be hidden when
 * no recovery codes are available.
 *
 * @param {HTMLElement|null} root
 * @returns {HTMLElement|null}
 */
export function getRecoverySectionWrapper(root) {
    if (!(root instanceof HTMLElement)) {
        return null;
    }

    const wrapper = queryOne('[data-authkit-two-factor-recovery-section]', root);

    return wrapper instanceof HTMLElement ? wrapper : null;
}

/**
 * Boot the two-factor settings page runtime module.
 *
 * @param {Object} context
 * @returns {Object|null}
 */
export function boot(context) {
    if (!isObject(context) || !isCurrentPage('two_factor_settings')) {
        return null;
    }

    const elements = getTwoFactorSettingsPageElements(context);

    /**
     * Enforce initial page state:
     * - code-mode disable form visible
     * - recovery disable fallback hidden
     * - default disable note visible
     * - recovery-code section shown only when actual codes exist
     */
    showDisableCodeMode(elements.root);
    syncRecoverySectionVisibility(elements.root);

    const cleanupToggle = bindDisableToggleButtons(elements.root);
    const cleanupDownload = bindRecoveryDownload(elements.root);
    const cleanupHydration = bindRecoverySuccessHydration(elements.root);

    return {
        key: 'two_factor_settings',
        ...elements,
        cleanup() {
            cleanupToggle();
            cleanupDownload();
            cleanupHydration();
        },
    };
}