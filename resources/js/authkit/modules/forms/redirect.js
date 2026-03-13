/**
 * AuthKit
 * -----------------------------------------------------------------------------
 * File: js/modules/forms/redirect.js
 * Author: Michael Erastus
 * Package: AuthKit
 *
 * Description:
 * -----------------------------------------------------------------------------
 * Redirect handling utilities for the AuthKit AJAX forms module.
 *
 * This file is responsible for resolving redirect behavior from the AuthKit
 * runtime configuration and executing browser navigation when a successful AJAX
 * submission carries redirect intent.
 *
 * Responsibilities:
 * - Resolve the configured post-success redirect behavior.
 * - Resolve the configured fallback redirect URL.
 * - Support both camelCase and snake_case runtime config keys.
 * - Execute browser redirects safely.
 * - Apply redirect intent from normalized successful form results.
 *
 * Design notes:
 * - This file does not perform HTTP requests.
 * - This file does not render UI feedback.
 * - This file does not decide whether a submission succeeded; it only responds
 *   to already-normalized success results.
 * - Redirect behavior is intentionally configuration-driven so future UI flows
 *   may choose between redirect, none, or custom event-based handling.
 *
 * Supported configuration sources:
 * - context.config.forms.successBehavior
 * - context.config.forms.success_behavior
 * - context.config.forms.ajax.successBehavior
 * - context.config.forms.ajax.success_behavior
 * - context.config.forms.fallbackRedirect
 * - context.config.forms.fallback_redirect
 * - context.config.forms.ajax.fallbackRedirect
 * - context.config.forms.ajax.fallback_redirect
 *
 * Expected normalized success result shape:
 * - {
 *     ok: true,
 *     redirectUrl: 'https://example.com/dashboard' | null
 *   }
 *
 * @package   AuthKit
 * @author    Michael Erastus
 * @license   MIT
 */

import { dataGet, isString, normalizeString } from '../../core/helpers.js';


/**
 * Resolve the configured success redirect behavior.
 *
 * Resolution order:
 * - context.config.forms.successBehavior
 * - context.config.forms.success_behavior
 * - context.config.forms.ajax.successBehavior
 * - context.config.forms.ajax.success_behavior
 * - redirect fallback
 *
 * Supported values:
 * - redirect
 * - none
 *
 * @param {Object|null} context
 * @returns {string}
 */
export function resolveRedirectBehavior(context) {
    return normalizeString(
        dataGet(
            context,
            'config.forms.successBehavior',
            dataGet(
                context,
                'config.forms.success_behavior',
                dataGet(
                    context,
                    'config.forms.ajax.successBehavior',
                    dataGet(context, 'config.forms.ajax.success_behavior', 'redirect')
                )
            )
        ),
        'redirect'
    );
}


/**
 * Resolve the configured fallback redirect URL.
 *
 * Resolution order:
 * - context.config.forms.fallbackRedirect
 * - context.config.forms.fallback_redirect
 * - context.config.forms.ajax.fallbackRedirect
 * - context.config.forms.ajax.fallback_redirect
 * - empty-string fallback
 *
 * @param {Object|null} context
 * @returns {string}
 */
export function resolveFallbackRedirect(context) {
    return normalizeString(
        dataGet(
            context,
            'config.forms.fallbackRedirect',
            dataGet(
                context,
                'config.forms.fallback_redirect',
                dataGet(
                    context,
                    'config.forms.ajax.fallbackRedirect',
                    dataGet(context, 'config.forms.ajax.fallback_redirect', '')
                )
            )
        ),
        ''
    );
}


/**
 * Perform a browser redirect safely.
 *
 * Returns false when the provided URL is invalid or empty.
 *
 * @param {*} url
 * @returns {boolean}
 */
export function performRedirect(url) {
    const normalizedUrl = normalizeString(url, '');

    if (normalizedUrl === '') {
        return false;
    }

    if (!window || !window.location || typeof window.location.assign !== 'function') {
        return false;
    }

    window.location.assign(normalizedUrl);

    return true;
}


/**
 * Apply redirect behavior for a normalized successful submission result.
 *
 * Rules:
 * - when configured behavior is not "redirect", do nothing
 * - prefer normalizedResult.redirectUrl when present
 * - otherwise fall back to configured fallback redirect
 *
 * @param {Object|null} context
 * @param {Object|null} normalizedResult
 * @returns {boolean}
 */
export function handleRedirect(context, normalizedResult) {
    const behavior = resolveRedirectBehavior(context);

    if (behavior !== 'redirect') {
        return false;
    }

    const redirectUrl = isString(normalizedResult?.redirectUrl)
        ? normalizeString(normalizedResult.redirectUrl, '')
        : '';

    if (redirectUrl !== '') {
        return performRedirect(redirectUrl);
    }

    const fallbackRedirect = resolveFallbackRedirect(context);

    if (fallbackRedirect !== '') {
        return performRedirect(fallbackRedirect);
    }

    return false;
}
