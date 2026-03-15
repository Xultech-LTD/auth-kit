/**
 * AuthKit
 * -----------------------------------------------------------------------------
 * File: js/modules/forms/loading.js
 * Author: Michael Erastus
 * Package: AuthKit
 *
 * Description:
 * -----------------------------------------------------------------------------
 * Loading-state utilities for the AuthKit forms runtime module.
 *
 * This file is responsible for applying and clearing the temporary submit-busy
 * UI shown while an AuthKit-managed form submission is in progress.
 *
 * Responsibilities:
 * - Resolve loading configuration from runtime context and per-call overrides.
 * - Discover submit-capable controls within a form.
 * - Capture original button state before loading UI is applied.
 * - Apply loading classes, disabled state, and temporary label changes.
 * - Support built-in loading presentation types.
 * - Restore original button state when submission completes.
 * - Apply and clear form-level accessibility busy state.
 *
 * Supported loading types:
 * - text
 * - spinner
 * - spinner_text
 * - custom_html
 *
 * Design notes:
 * - This file does not submit forms directly.
 * - This file does not mutate normalized server results.
 * - This file only manages temporary DOM state during submission.
 * - Original button state is stored in WeakMaps so detached elements are not
 *   retained unnecessarily.
 *
 * @package   AuthKit
 * @author    Michael Erastus
 * @license   MIT
 */

import { getAttribute, queryAll, queryOne } from '../../core/dom.js';
import { dataGet, isObject, isString, normalizeString } from '../../core/helpers.js';


/**
 * Internal storage for original button state.
 *
 * Stored shape:
 * - {
 *     disabled: boolean,
 *     labelHtml: string,
 *     loaderHtml: string,
 *     loaderHidden: boolean,
 *     ariaBusy: string|null
 *   }
 *
 * @type {WeakMap<HTMLElement, Object>}
 */
const buttonStateStore = new WeakMap();


/**
 * Resolve normalized loading configuration.
 *
 * Resolution order:
 * - options.loading.{key}
 * - context.config.forms.loading.{key}
 * - built-in fallback
 *
 * @param {Object|null} context
 * @param {Object} [options={}]
 * @returns {{
 *   enabled: boolean,
 *   preventDoubleSubmit: boolean,
 *   disableSubmit: boolean,
 *   setAriaBusy: boolean,
 *   type: string,
 *   text: string,
 *   showText: boolean,
 *   html: string|null,
 *   className: string
 * }}
 */
export function getLoadingConfig(context = null, options = {}) {
    const optionLoading = isObject(dataGet(options, 'loading', null))
        ? dataGet(options, 'loading', {})
        : {};

    const contextLoading = isObject(dataGet(context, 'config.forms.loading', null))
        ? dataGet(context, 'config.forms.loading', {})
        : {};

    return {
        enabled: Boolean(dataGet(optionLoading, 'enabled', dataGet(contextLoading, 'enabled', true))),
        preventDoubleSubmit: Boolean(
            dataGet(optionLoading, 'preventDoubleSubmit', dataGet(contextLoading, 'preventDoubleSubmit', true))
        ),
        disableSubmit: Boolean(
            dataGet(optionLoading, 'disableSubmit', dataGet(contextLoading, 'disableSubmit', true))
        ),
        setAriaBusy: Boolean(
            dataGet(optionLoading, 'setAriaBusy', dataGet(contextLoading, 'setAriaBusy', true))
        ),
        type: normalizeString(
            dataGet(optionLoading, 'type', dataGet(contextLoading, 'type', 'spinner_text')),
            'spinner_text'
        ),
        text: normalizeString(
            dataGet(optionLoading, 'text', dataGet(contextLoading, 'text', 'Processing...')),
            'Processing...'
        ),
        showText: Boolean(
            dataGet(optionLoading, 'showText', dataGet(contextLoading, 'showText', true))
        ),
        html: isString(dataGet(optionLoading, 'html', dataGet(contextLoading, 'html', null)))
            ? dataGet(optionLoading, 'html', dataGet(contextLoading, 'html', null))
            : null,
        className: normalizeString(
            dataGet(optionLoading, 'className', dataGet(contextLoading, 'className', 'authkit-btn--loading')),
            'authkit-btn--loading'
        ),
    };
}


/**
 * Resolve all submit-capable controls in a form.
 *
 * Supported controls:
 * - <button> with missing type
 * - <button type="submit">
 * - <input type="submit">
 * - <input type="image">
 *
 * @param {HTMLFormElement|null} form
 * @returns {HTMLElement[]}
 */
export function getSubmitButtons(form) {
    if (!(form instanceof HTMLFormElement)) {
        return [];
    }

    return queryAll(
        'button:not([type]), button[type="submit"], input[type="submit"], input[type="image"]',
        form
    ).filter((element) => element instanceof HTMLElement);
}


/**
 * Resolve the label element within a button, when present.
 *
 * @param {HTMLElement|null} button
 * @returns {HTMLElement|null}
 */
export function getButtonLabelElement(button) {
    if (!(button instanceof HTMLElement)) {
        return null;
    }

    const label = queryOne('[data-authkit-button-label="1"]', button);

    return label instanceof HTMLElement ? label : null;
}


/**
 * Resolve the loader element within a button, when present.
 *
 * @param {HTMLElement|null} button
 * @returns {HTMLElement|null}
 */
export function getButtonLoaderElement(button) {
    if (!(button instanceof HTMLElement)) {
        return null;
    }

    const loader = queryOne('[data-authkit-button-loader="1"]', button);

    return loader instanceof HTMLElement ? loader : null;
}


/**
 * Resolve the loading type for a specific submit control.
 *
 * Per-button data attributes take precedence over config.
 *
 * Supported attribute:
 * - data-authkit-loading-type
 *
 * @param {HTMLElement|null} button
 * @param {Object} config
 * @returns {string}
 */
export function resolveButtonLoadingType(button, config) {
    const attributeType = normalizeString(
        getAttribute(button, 'data-authkit-loading-type', ''),
        ''
    );

    if (attributeType !== '') {
        return attributeType;
    }

    return normalizeString(config?.type, 'spinner_text');
}


/**
 * Resolve the loading text for a specific submit control.
 *
 * Per-button data attributes take precedence over config.
 *
 * Supported attribute:
 * - data-authkit-loading-text
 *
 * @param {HTMLElement|null} button
 * @param {Object} config
 * @returns {string}
 */
export function resolveButtonLoadingText(button, config) {
    const attributeText = normalizeString(
        getAttribute(button, 'data-authkit-loading-text', ''),
        ''
    );

    if (attributeText !== '') {
        return attributeText;
    }

    return normalizeString(config?.text, 'Processing...');
}


/**
 * Capture and store the original state of a submit control.
 *
 * This happens only once per control until cleared.
 *
 * @param {HTMLElement|null} button
 * @returns {Object|null}
 */
export function captureButtonState(button) {
    if (!(button instanceof HTMLElement)) {
        return null;
    }

    const existing = buttonStateStore.get(button);

    if (existing) {
        return existing;
    }

    const labelElement = getButtonLabelElement(button);
    const loaderElement = getButtonLoaderElement(button);

    const snapshot = {
        disabled: 'disabled' in button ? Boolean(button.disabled) : false,
        labelHtml: labelElement ? labelElement.innerHTML : '',
        loaderHtml: loaderElement ? loaderElement.innerHTML : '',
        loaderHidden: loaderElement ? loaderElement.hidden === true : false,
        ariaBusy: getAttribute(button, 'aria-busy', null),
    };

    buttonStateStore.set(button, snapshot);

    return snapshot;
}


/**
 * Apply loading UI to a single submit control.
 *
 * @param {HTMLElement|null} button
 * @param {Object} config
 * @returns {boolean}
 */
export function applyButtonLoadingState(button, config) {
    if (!(button instanceof HTMLElement)) {
        return false;
    }

    captureButtonState(button);

    const labelElement = getButtonLabelElement(button);
    const loaderElement = getButtonLoaderElement(button);

    const loadingType = resolveButtonLoadingType(button, config);
    const loadingText = resolveButtonLoadingText(button, config);
    const className = normalizeString(config?.className, 'authkit-btn--loading');

    button.classList.add(className);
    button.setAttribute('data-authkit-loading', 'true');
    button.setAttribute('aria-busy', 'true');

    if (config?.disableSubmit === true && 'disabled' in button) {
        button.disabled = true;
    }

    if (loaderElement) {
        loaderElement.hidden = loadingType === 'text';

        if (loadingType === 'custom_html') {
            loaderElement.innerHTML = isString(config?.html) ? config.html : '';
        } else {
            loaderElement.innerHTML = '';
        }
    }

    if (labelElement) {
        if (loadingType === 'spinner') {
            labelElement.textContent = '';
        } else if (config?.showText === false) {
            labelElement.textContent = '';
        } else {
            labelElement.textContent = loadingText;
        }
    }

    return true;
}


/**
 * Restore the original UI state of a single submit control.
 *
 * @param {HTMLElement|null} button
 * @param {Object} config
 * @returns {boolean}
 */
export function clearButtonLoadingState(button, config) {
    if (!(button instanceof HTMLElement)) {
        return false;
    }

    const snapshot = buttonStateStore.get(button);

    if (!snapshot) {
        return false;
    }

    const labelElement = getButtonLabelElement(button);
    const loaderElement = getButtonLoaderElement(button);
    const className = normalizeString(config?.className, 'authkit-btn--loading');

    button.classList.remove(className);
    button.removeAttribute('data-authkit-loading');

    if (snapshot.ariaBusy === null) {
        button.removeAttribute('aria-busy');
    } else {
        button.setAttribute('aria-busy', snapshot.ariaBusy);
    }

    if ('disabled' in button) {
        button.disabled = snapshot.disabled === true;
    }

    if (labelElement) {
        labelElement.innerHTML = snapshot.labelHtml;
    }

    if (loaderElement) {
        loaderElement.innerHTML = snapshot.loaderHtml;
        loaderElement.hidden = snapshot.loaderHidden === true;
    }

    buttonStateStore.delete(button);

    return true;
}


/**
 * Apply loading state to all submit controls within a form.
 *
 * @param {HTMLFormElement|null} form
 * @param {Object|null} context
 * @param {Object} [options={}]
 * @returns {number}
 */
export function applyLoadingState(form, context = null, options = {}) {
    if (!(form instanceof HTMLFormElement)) {
        return 0;
    }

    const config = getLoadingConfig(context, options);

    if (config.enabled !== true) {
        return 0;
    }

    if (config.setAriaBusy === true) {
        form.setAttribute('aria-busy', 'true');
    }

    form.setAttribute('data-authkit-submitting', 'true');

    const buttons = getSubmitButtons(form);

    return buttons.reduce((count, button) => {
        return applyButtonLoadingState(button, config) ? count + 1 : count;
    }, 0);
}


/**
 * Clear loading state from all submit controls within a form.
 *
 * @param {HTMLFormElement|null} form
 * @param {Object|null} context
 * @param {Object} [options={}]
 * @returns {number}
 */
export function clearLoadingState(form, context = null, options = {}) {
    if (!(form instanceof HTMLFormElement)) {
        return 0;
    }

    const config = getLoadingConfig(context, options);
    const buttons = getSubmitButtons(form);

    form.removeAttribute('data-authkit-submitting');
    form.removeAttribute('aria-busy');

    return buttons.reduce((count, button) => {
        return clearButtonLoadingState(button, config) ? count + 1 : count;
    }, 0);
}