/**
 * AuthKit
 * -----------------------------------------------------------------------------
 * File: js/core/dom.js
 * Author: Michael Erastus
 * Package: AuthKit
 *
 * Description:
 * -----------------------------------------------------------------------------
 * DOM utility helpers used across the AuthKit browser runtime.
 *
 * This file centralizes common DOM lookups and small DOM-related helpers so that
 * runtime modules do not repeatedly re-implement low-level browser access logic.
 *
 * Responsibilities:
 * - Resolve core AuthKit root elements.
 * - Discover page markers and page metadata.
 * - Discover theme toggle controls.
 * - Discover AJAX-enabled forms.
 * - Provide safe, small DOM convenience helpers.
 *
 * Design notes:
 * - This file may depend on browser DOM APIs.
 * - This file must remain framework-agnostic.
 * - This file should not contain business logic for modules; it only provides
 *   reusable DOM primitives used by higher-level runtime code.
 *
 * @package   AuthKit
 * @author    Michael Erastus
 * @license   MIT
 */

import {isFunction, isString, normalizeString, toArray} from './helpers.js';


/**
 * Resolve the root <html> element for the current document.
 *
 * @returns {HTMLElement|null}
 */
export function getDocumentRoot() {
    return document.documentElement || null;
}


/**
 * Resolve the current document <body> element.
 *
 * @returns {HTMLBodyElement|null}
 */
export function getBody() {
    return document.body || null;
}


/**
 * Resolve the first element matching a selector within the provided scope.
 *
 * @param {string} selector
 * @param {ParentNode|Document|Element} [scope=document]
 * @returns {Element|null}
 */
export function queryOne(selector, scope = document) {
    if (!isString(selector) || selector.trim() === '') {
        return null;
    }

    if (!scope || !isFunction(scope.querySelector)) {
        return null;
    }

    return scope.querySelector(selector);
}


/**
 * Resolve all elements matching a selector within the provided scope.
 *
 * @param {string} selector
 * @param {ParentNode|Document|Element} [scope=document]
 * @returns {Element[]}
 */
export function queryAll(selector, scope = document) {
    if (!isString(selector) || selector.trim() === '') {
        return [];
    }

    if (!scope || !isFunction(scope.querySelectorAll)) {
        return [];
    }

    return Array.from(scope.querySelectorAll(selector));
}


/**
 * Determine whether the given element is a DOM Element.
 *
 * @param {*} value
 * @returns {boolean}
 */
export function isElement(value) {
    return value instanceof Element;
}


/**
 * Determine whether the given element is a DOM Element.
 *
 * @param {*} value
 * @returns {boolean}
 */
export function isHTMLFormElement (value) {
    return value instanceof HTMLFormElement ;
}


/**
 * Read a data attribute from an element.
 *
 * The provided name may be passed either as:
 * - full attribute form: "data-authkit-page"
 * - dataset-like key form: "authkitPage"
 *
 * For consistency inside AuthKit, full data-attribute names are preferred.
 *
 * @param {Element|null} element
 * @param {string} name
 * @param {string|null} defaultValue
 * @returns {string|null}
 */
export function getDataAttribute(element, name, defaultValue = null) {
    if (!isElement(element) || !isString(name) || name.trim() === '') {
        return defaultValue;
    }

    const normalizedName = name.trim();

    if (normalizedName.startsWith('data-')) {
        const value = element.getAttribute(normalizedName);

        return value !== null ? value : defaultValue;
    }

    const value = element.dataset?.[normalizedName];

    return value !== undefined ? value : defaultValue;
}


/**
 * Set a data attribute on an element.
 *
 * @param {Element|null} element
 * @param {string} name
 * @param {string} value
 * @returns {void}
 */
export function setDataAttribute(element, name, value) {
    if (!isElement(element) || !isString(name) || name.trim() === '') {
        return;
    }

    const normalizedName = name.trim();

    if (normalizedName.startsWith('data-')) {
        element.setAttribute(normalizedName, String(value));
        return;
    }

    if (element.dataset) {
        element.dataset[normalizedName] = String(value);
    }
}


/**
 * Determine whether an element has a given data attribute.
 *
 * @param {Element|null} element
 * @param {string} name
 * @returns {boolean}
 */
export function hasDataAttribute(element, name) {
    if (!isElement(element) || !isString(name) || name.trim() === '') {
        return false;
    }

    const normalizedName = name.trim();

    if (normalizedName.startsWith('data-')) {
        return element.hasAttribute(normalizedName);
    }

    return element.dataset ? normalizedName in element.dataset : false;
}


/**
 * Resolve the main AuthKit page element.
 *
 * AuthKit pages are expected to expose a page-level marker using:
 * - .authkit-page
 *
 * @returns {HTMLElement|null}
 */
export function getPageElement() {
    const page = queryOne('[data-authkit-page-root], .authkit-page, body[data-authkit-page]');

    return page instanceof HTMLElement ? page : null;
}


/**
 * Resolve the current AuthKit page key from the page marker.
 *
 * Expected marker:
 * - data-authkit-page="login"
 *
 * @returns {string|null}
 */
export function getPageKey() {
    const page = getPageElement();

    if (!page) {
        return null;
    }

    return normalizeString(getDataAttribute(page, 'data-authkit-page'), null);
}

/**
 * Read a normal attribute from an element.
 *
 * Returns the provided default value when:
 * - the element is invalid
 * - the attribute name is invalid
 * - the attribute is missing
 *
 * @param {Element|null} element
 * @param {string} name
 * @param {string|null} defaultValue
 * @returns {string|null}
 */
export function getAttribute(element, name, defaultValue = null) {
    if (!isElement(element) || !isString(name) || name.trim() === '') {
        return defaultValue;
    }

    const value = element.getAttribute(name.trim());

    return value !== null ? value : defaultValue;
}

/**
 * Determine whether the current page matches the provided AuthKit page key.
 *
 * @param {string} expectedPageKey
 * @returns {boolean}
 */
export function isPage(expectedPageKey) {
    const currentPageKey = getPageKey();
    const normalizedExpected = normalizeString(expectedPageKey, '');

    if (normalizedExpected === '' || currentPageKey === null) {
        return false;
    }

    return currentPageKey === normalizedExpected;
}


/**
 * Resolve all AuthKit theme toggle option elements.
 *
 * These are typically buttons such as:
 * - data-authkit-theme-toggle="light"
 * - data-authkit-theme-toggle="dark"
 * - data-authkit-theme-toggle="system"
 *
 * @param {string} toggleAttribute
 * @returns {HTMLElement[]}
 */
export function getThemeToggleOptions(toggleAttribute = 'data-authkit-theme-toggle') {
    const selector = `[${toggleAttribute}]`;

    return queryAll(selector).filter((element) => element instanceof HTMLElement);
}


/**
 * Resolve all AuthKit theme toggle select elements.
 *
 * Expected marker:
 * - data-authkit-theme-toggle-select="1"
 *
 * @returns {HTMLSelectElement[]}
 */
export function getThemeToggleSelects() {
    return queryAll('[data-authkit-theme-toggle-select]').filter(
        (element) => element instanceof HTMLSelectElement
    );
}


/**
 * Resolve all AuthKit theme toggle cycle buttons.
 *
 * Expected marker:
 * - data-authkit-theme-toggle-cycle="1"
 *
 * @returns {HTMLButtonElement[]}
 */
export function getThemeToggleCycleButtons() {
    return queryAll('[data-authkit-theme-toggle-cycle]').filter(
        (element) => element instanceof HTMLButtonElement
    );
}


/**
 * Resolve all AuthKit forms using the configured AJAX attribute.
 *
 * Example marker:
 * - data-authkit-ajax="1"
 *
 * @param {string} ajaxAttribute
 * @returns {HTMLFormElement[]}
 */
export function getAjaxForms(ajaxAttribute = 'data-authkit-ajax') {
    const selector = `[${ajaxAttribute}]`;

    return queryAll(selector).filter((element) => element instanceof HTMLFormElement);
}


/**
 * Resolve all forms in the current page scope.
 *
 * @param {ParentNode|Document|Element} [scope=document]
 * @returns {HTMLFormElement[]}
 */
export function getForms(scope = document) {
    return queryAll('form', scope).filter((element) => element instanceof HTMLFormElement);
}


/**
 * Resolve the first matching ancestor for an element.
 *
 * @param {Element|null} element
 * @param {string} selector
 * @returns {Element|null}
 */
export function closest(element, selector) {
    if (!isElement(element) || !isString(selector) || selector.trim() === '') {
        return null;
    }

    return element.closest(selector);
}


/**
 * Attach an event listener and return a cleanup callback.
 *
 * @param {EventTarget|null} target
 * @param {string} eventName
 * @param {EventListenerOrEventListenerObject} listener
 * @param {boolean|AddEventListenerOptions} [options]
 * @returns {Function}
 */
export function listen(target, eventName, listener, options = false) {
    if (!target || !isFunction(target.addEventListener) ) {
        return () => {};
    }

    if (!isString(eventName) || eventName.trim() === '') {
        return () => {};
    }

    target.addEventListener(eventName, listener, options);

    return () => {
        if (isFunction(target.removeEventListener)) {
            target.removeEventListener(eventName, listener, options);
        }
    };
}


/**
 * Add a class to an element when valid.
 *
 * @param {Element|null} element
 * @param {string|string[]} classNames
 * @returns {void}
 */
export function addClass(element, classNames) {
    if (!isElement(element)) {
        return;
    }

    toArray(classNames)
        .filter((className) => isString(className) && className.trim() !== '')
        .forEach((className) => {
            element.classList.add(className.trim());
        });
}


/**
 * Remove a class from an element when valid.
 *
 * @param {Element|null} element
 * @param {string|string[]} classNames
 * @returns {void}
 */
export function removeClass(element, classNames) {
    if (!isElement(element)) {
        return;
    }

    toArray(classNames)
        .filter((className) => isString(className) && className.trim() !== '')
        .forEach((className) => {
            element.classList.remove(className.trim());
        });
}


/**
 * Determine whether the DOM is already ready for runtime boot.
 *
 * @returns {boolean}
 */
export function isDomReady() {
    return document.readyState === 'interactive' || document.readyState === 'complete';
}


/**
 * Execute a callback as soon as the DOM is ready.
 *
 * If the DOM is already ready, the callback executes immediately.
 *
 * @param {Function} callback
 * @returns {void}
 */
export function onDomReady(callback) {
    if (!isFunction(callback)) {
        return;
    }

    if (isDomReady()) {
        callback();
        return;
    }

    document.addEventListener('DOMContentLoaded', callback, { once: true });
}