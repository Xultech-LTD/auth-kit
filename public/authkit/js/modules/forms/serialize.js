/**
 * AuthKit
 * -----------------------------------------------------------------------------
 * File: js/modules/forms/serialize.js
 * Author: Michael Erastus
 * Package: AuthKit
 *
 * Description:
 * -----------------------------------------------------------------------------
 * Form serialization utilities for the AuthKit AJAX forms module.
 *
 * This file is responsible for converting a browser form into normalized data
 * structures that can be submitted through the AuthKit HTTP layer.
 *
 * Responsibilities:
 * - Resolve the effective HTTP method for a form.
 * - Resolve the effective submission URL for a form.
 * - Serialize a form into FormData.
 * - Serialize a form into a plain object payload.
 * - Preserve repeated field values in a predictable way.
 * - Keep serialization logic separate from submission orchestration.
 *
 * Design notes:
 * - This file does not perform HTTP requests.
 * - This file does not mutate UI state.
 * - This file does not render errors or success feedback.
 * - Serialization intentionally supports both FormData and plain-object output
 *   because different submission modes may be used by the forms module or
 *   page-level extensions.
 *
 * Serialization rules:
 * - Disabled controls are ignored by the browser FormData API.
 * - Repeated field names are grouped into arrays in object output.
 * - Empty field names are ignored in object output.
 * - File inputs are preserved in FormData output and passed through in object
 *   output when present.
 *
 * @package   AuthKit
 * @author    Michael Erastus
 * @license   MIT
 */

import { getAttribute, isElement } from '../../core/dom.js';
import {isObject, isString, isUndefined, normalizeString} from '../../core/helpers.js';


/**
 * Resolve the effective HTTP method for a form submission.
 *
 * Resolution order:
 * - form.method
 * - method attribute
 * - GET fallback
 *
 * Returned methods are always uppercase.
 *
 * @param {HTMLFormElement|null} form
 * @returns {string}
 */
export function getFormMethod(form) {
    if (!isFormElement(form)) {
        return 'GET';
    }

    const method = normalizeString(form.method, '')
        || normalizeString(getAttribute(form, 'method', ''), '');

    return method !== '' ? method.toUpperCase() : 'GET';
}


/**
 * Resolve the effective submission URL for a form.
 *
 * Resolution order:
 * - form.action
 * - action attribute
 * - current window location
 * - empty string fallback
 *
 * @param {HTMLFormElement|null} form
 * @returns {string}
 */
export function getFormAction(form) {
    if (!isFormElement(form)) {
        return getCurrentUrl();
    }

    const action = normalizeString(form.action, '')
        || normalizeString(getAttribute(form, 'action', ''), '');

    if (action !== '') {
        return action;
    }

    return getCurrentUrl();
}


/**
 * Determine whether the supplied value is a form element.
 *
 * @param {*} value
 * @returns {boolean}
 */
export function isFormElement(value) {
    return isElement(value) && value instanceof HTMLFormElement;
}


/**
 * Serialize a form into a FormData instance.
 *
 * This uses the browser's native FormData behavior so:
 * - file inputs are preserved
 * - successful controls are included
 * - disabled fields are ignored
 *
 * @param {HTMLFormElement|null} form
 * @returns {FormData}
 */
export function serializeFormToFormData(form) {
    if (!isFormElement(form)) {
        return new FormData();
    }

    return new FormData(form);
}


/**
 * Serialize a form into a normalized plain object payload.
 *
 * Repeated field names are grouped into arrays.
 * Single values remain scalar where possible.
 *
 * Example output:
 * - { email: 'user@example.com', remember: '1' }
 * - { roles: ['admin', 'editor'] }
 *
 * @param {HTMLFormElement|null} form
 * @returns {Record<string, *>}
 */
export function serializeForm(form) {
    return formDataToObject(serializeFormToFormData(form));
}


/**
 * Convert a FormData instance into a normalized plain object payload.
 *
 * Rules:
 * - first value for a name is stored directly
 * - subsequent values are grouped into an array
 *
 * @param {FormData|null} formData
 * @returns {Record<string, *>}
 */
export function formDataToObject(formData) {
    if (!(formData instanceof FormData)) {
        return {};
    }

    const payload = {};

    for (const [name, value] of formData.entries()) {
        const normalizedName = normalizeString(name, '');

        if (normalizedName === '') {
            continue;
        }

        if (!Object.prototype.hasOwnProperty.call(payload, normalizedName)) {
            payload[normalizedName] = value;
            continue;
        }

        if (!Array.isArray(payload[normalizedName])) {
            payload[normalizedName] = [payload[normalizedName]];
        }

        payload[normalizedName].push(value);
    }

    return payload;
}


/**
 * Build a normalized submission descriptor for a form.
 *
 * This descriptor is useful for downstream submit handlers because it collects
 * all submission-critical values in one place.
 *
 * Returned shape:
 * - form: HTMLFormElement|null
 * - action: string
 * - method: string
 * - formData: FormData
 * - data: Record<string, *>
 *
 * @param {HTMLFormElement|null} form
 * @returns {{
 *   form: HTMLFormElement|null,
 *   action: string,
 *   method: string,
 *   formData: FormData,
 *   data: Record<string, *>
 * }}
 */
export function buildSerializedForm(form) {
    const normalizedForm = isFormElement(form) ? form : null;
    const formData = serializeFormToFormData(normalizedForm);

    return {
        form: normalizedForm,
        action: getFormAction(normalizedForm),
        method: getFormMethod(normalizedForm),
        formData,
        data: formDataToObject(formData),
    };
}


/**
 * Merge additional values into a serialized payload object.
 *
 * Rules:
 * - invalid base payload becomes an empty object
 * - invalid extra payload is ignored
 * - extra values overwrite base values at the top level
 *
 * @param {*} payload
 * @param {*} extra
 * @returns {Record<string, *>}
 */
export function mergeSerializedData(payload, extra = {}) {
    const base = isObject(payload) ? payload : {};
    const additions = isObject(extra) ? extra : {};

    return {
        ...base,
        ...additions,
    };
}


/**
 * Read a single serialized field value from a payload.
 *
 * @param {*} payload
 * @param {string} field
 * @param {*} fallback
 * @returns {*}
 */
export function getSerializedValue(payload, field, fallback = null) {
    if (!isObject(payload)) {
        return fallback;
    }

    const normalizedField = normalizeString(field, '');

    if (normalizedField === '') {
        return fallback;
    }

    return Object.prototype.hasOwnProperty.call(payload, normalizedField)
        ? payload[normalizedField]
        : fallback;
}


/**
 * Determine whether a serialized payload has a given field.
 *
 * @param {*} payload
 * @param {string} field
 * @returns {boolean}
 */
export function hasSerializedValue(payload, field) {
    if (!isObject(payload)) {
        return false;
    }

    const normalizedField = normalizeString(field, '');

    if (normalizedField === '') {
        return false;
    }

    return Object.prototype.hasOwnProperty.call(payload, normalizedField);
}


/**
 * Resolve the current browser URL safely.
 *
 * @returns {string}
 */
export function getCurrentUrl() {
    if (isUndefined(window) || !isString(window.location?.href)) {
        return '';
    }

    return normalizeString(window.location.href, '');
}