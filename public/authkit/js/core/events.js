/**
 * AuthKit
 * -----------------------------------------------------------------------------
 * File: events.js
 * Author: Michael Erastus
 * Package: AuthKit
 *
 * Description:
 * -----------------------------------------------------------------------------
 * Browser event utilities for the AuthKit client runtime.
 *
 * This file centralizes runtime event resolution and dispatch so that:
 * - modules do not hard-code event names repeatedly
 * - consumers can override event names from configuration
 * - runtime code can emit consistent CustomEvent payloads
 *
 * Responsibilities:
 * - Resolve configured AuthKit event names from runtime configuration.
 * - Resolve the configured browser event target (document or window).
 * - Dispatch CustomEvent instances in a safe and consistent way.
 * - Provide small helper APIs for listening to AuthKit runtime events.
 *
 * Design notes:
 * - Event names are runtime-configurable and should be treated as data.
 * - The runtime should fail gracefully if event dispatching is disabled.
 * - Event dispatch is framework-agnostic and relies only on browser APIs.
 *
 * @package   AuthKit
 * @author    Michael Erastus
 * @license   MIT
 */

import { getConfigValue } from './config.js';
import {isFunction, isObject, isString, isUndefined, normalizeString} from './helpers.js';


/**
 * Default AuthKit browser event names.
 *
 * These are used when runtime configuration does not provide an override.
 *
 * @type {Record<string, string>}
 */
const DEFAULT_EVENT_NAMES = {
    ready: 'authkit:ready',
    theme_ready: 'authkit:theme:ready',
    theme_changed: 'authkit:theme:changed',
    form_before_submit: 'authkit:form:before-submit',
    form_success: 'authkit:form:success',
    form_error: 'authkit:form:error',
    page_ready: 'authkit:page:ready',
};


/**
 * Resolve whether AuthKit runtime event dispatching is enabled.
 *
 * @returns {boolean}
 */
export function shouldDispatchEvents() {
    return Boolean(
        getConfigValue('runtime.dispatchEvents', true)
    );
}


/**
 * Resolve the configured runtime event target key.
 *
 * Supported values:
 * - document
 * - window
 *
 * @returns {string}
 */
export function getEventTargetKey() {
    return normalizeString(
        getConfigValue('runtime.eventTarget', 'document'),
        'document'
    );
}


/**
 * Resolve the actual browser EventTarget used for runtime events.
 *
 * Falls back to `document` when configuration is unknown or unavailable.
 *
 * @returns {EventTarget}
 */
export function getEventTarget() {
    const eventTargetKey = getEventTargetKey();

    if (eventTargetKey === 'window' && !isUndefined(window) ) {
        return window;
    }

    return document;
}


/**
 * Resolve the configured AuthKit event name for a logical event key.
 *
 * Example:
 * - logical key: "theme_changed"
 * - resolved name: "authkit:theme:changed"
 *
 * @param {string} key
 * @param {string|null} fallback
 * @returns {string|null}
 */
export function getEventName(key, fallback = null) {
    if (!isString(key) || key.trim() === '') {
        return fallback;
    }

    const normalizedKey = key.trim();
    const defaultName = DEFAULT_EVENT_NAMES[normalizedKey] ?? fallback ?? null;
    const configuredName = getConfigValue(`events.${normalizedKey}`, defaultName);

    return normalizeString(configuredName, defaultName);
}


/**
 * Build a standard AuthKit event detail payload.
 *
 * The payload is normalized into a consistent shape to make consumer-side
 * event handling more predictable.
 *
 * @param {string} logicalEventKey
 * @param {Object} [detail={}]
 * @returns {Object}
 */
export function createEventDetail(logicalEventKey, detail = {}) {
    const payload = detail && isObject(detail)  ? { ...detail } : {};

    return {
        eventKey: logicalEventKey,
        timestamp: Date.now(),
        ...payload,
    };
}


/**
 * Dispatch an AuthKit CustomEvent.
 *
 * When runtime event dispatch is disabled, this function does nothing and
 * returns null.
 *
 * @param {string} logicalEventKey
 * @param {Object} [detail={}]
 * @returns {CustomEvent|null}
 */
export function dispatchEvent(logicalEventKey, detail = {}) {
    if (!shouldDispatchEvents()) {
        return null;
    }

    const eventName = getEventName(logicalEventKey);

    if (!isString(eventName) || eventName.trim() === '') {
        return null;
    }

    const target = getEventTarget();

    if (!target || !isFunction(target.dispatchEvent) ) {
        return null;
    }

    const event = new CustomEvent(eventName, {
        detail: createEventDetail(logicalEventKey, detail),
        bubbles: true,
    });

    target.dispatchEvent(event);

    return event;
}


/**
 * Attach a listener for a logical AuthKit event key.
 *
 * This helper resolves the configured event name automatically.
 *
 * @param {string} logicalEventKey
 * @param {Function} listener
 * @param {boolean|AddEventListenerOptions} [options=false]
 * @returns {Function}
 */
export function onEvent(logicalEventKey, listener, options = false) {
    const eventName = getEventName(logicalEventKey);

    if (!isString(eventName) || eventName.trim() === '') {
        return () => {};
    }

    const target = getEventTarget();

    if (!target || !isFunction(target.addEventListener)) {
        return () => {};
    }

    target.addEventListener(eventName, listener, options);

    return () => {
        if ( isFunction(target.removeEventListener) ) {
            target.removeEventListener(eventName, listener, options);
        }
    };
}


/**
 * Attach a listener for the AuthKit runtime ready event.
 *
 * @param {Function} listener
 * @param {boolean|AddEventListenerOptions} [options=false]
 * @returns {Function}
 */
export function onReady(listener, options = false) {
    return onEvent('ready', listener, options);
}


/**
 * Attach a listener for the AuthKit theme changed event.
 *
 * @param {Function} listener
 * @param {boolean|AddEventListenerOptions} [options=false]
 * @returns {Function}
 */
export function onThemeChanged(listener, options = false) {
    return onEvent('theme_changed', listener, options);
}


/**
 * Attach a listener for the AuthKit form success event.
 *
 * @param {Function} listener
 * @param {boolean|AddEventListenerOptions} [options=false]
 * @returns {Function}
 */
export function onFormSuccess(listener, options = false) {
    return onEvent('form_success', listener, options);
}


/**
 * Attach a listener for the AuthKit form error event.
 *
 * @param {Function} listener
 * @param {boolean|AddEventListenerOptions} [options=false]
 * @returns {Function}
 */
export function onFormError(listener, options = false) {
    return onEvent('form_error', listener, options);
}


/**
 * Attach a listener for the AuthKit page ready event.
 *
 * @param {Function} listener
 * @param {boolean|AddEventListenerOptions} [options=false]
 * @returns {Function}
 */
export function onPageReady(listener, options = false) {
    return onEvent('page_ready', listener, options);
}


/**
 * Expose the default event map for internal tooling or diagnostics.
 *
 * @returns {Record<string, string>}
 */
export function getDefaultEventNames() {
    return { ...DEFAULT_EVENT_NAMES };
}