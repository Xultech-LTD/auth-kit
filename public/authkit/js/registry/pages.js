/**
 * AuthKit
 * -----------------------------------------------------------------------------
 * File: js/registry/pages.js
 * Author: Michael Erastus
 * Package: AuthKit
 *
 * Description:
 * -----------------------------------------------------------------------------
 * Page runtime module registry for the AuthKit browser client.
 *
 * This registry provides the canonical map of bootable page modules that may be
 * initialized by the AuthKit runtime when a matching AuthKit page marker is
 * present in the DOM.
 *
 * Responsibilities:
 * - Register built-in page runtime modules.
 * - Provide a stable lookup map for runtime page boot orchestration.
 * - Keep page registration centralized and easy to extend.
 *
 * Design notes:
 * - Page modules are page-specific enhancements, not shared runtime modules.
 * - Modules listed here should expose a `boot(context)` function.
 * - Registry keys should align with configured page keys where possible.
 * - This file should remain declarative and avoid embedding page logic.
 *
 * @package   AuthKit
 * @author    Michael Erastus
 * @license   MIT
 */

import * as loginPage from '../pages/login.js';
import * as registerPage from '../pages/register.js';
import * as twoFactorChallengePage from '../pages/two-factor-challenge.js';
import * as twoFactorRecoveryPage from '../pages/two-factor-recovery.js';
import * as emailVerificationNoticePage from '../pages/email-verification-notice.js';
import * as emailVerificationTokenPage from '../pages/email-verification-token.js';
import * as emailVerificationSuccessPage from '../pages/email-verification-success.js';
import * as passwordForgotPage from '../pages/password-forgot.js';
import * as passwordForgotSentPage from '../pages/password-forgot-sent.js';
import * as passwordResetPage from '../pages/password-reset.js';
import * as passwordResetTokenPage from '../pages/password-reset-token.js';
import * as passwordResetSuccessPage from '../pages/password-reset-success.js';
import { normalizeString } from '../core/helpers.js';


/**
 * Built-in AuthKit page runtime registry.
 *
 * Keys must align with configuration under:
 * - authkit.javascript.pages.{key}
 *
 * Example:
 * - login
 * - register
 * - password_reset
 *
 * @type {Record<string, Object>}
 */
export const pageRegistry = Object.freeze({
    login: loginPage,
    register: registerPage,
    two_factor_challenge: twoFactorChallengePage,
    two_factor_recovery: twoFactorRecoveryPage,
    email_verification_notice: emailVerificationNoticePage,
    email_verification_token: emailVerificationTokenPage,
    email_verification_success: emailVerificationSuccessPage,
    password_forgot: passwordForgotPage,
    password_forgot_sent: passwordForgotSentPage,
    password_reset: passwordResetPage,
    password_reset_token: passwordResetTokenPage,
    password_reset_success: passwordResetSuccessPage,
});


/**
 * Resolve the full AuthKit page runtime registry.
 *
 * A shallow clone is returned to avoid accidental external mutation.
 *
 * @returns {Record<string, Object>}
 */
export function getPageRegistry() {
    return { ...pageRegistry };
}


/**
 * Resolve a single AuthKit page runtime module by key.
 *
 * @param {string} key
 * @returns {Object|null}
 */
export function getPageModule(key) {
    const normalizedKey = normalizeString(key, '');

    if (normalizedKey === '') {
        return null;
    }

    return pageRegistry[normalizedKey] ?? null;
}


/**
 * Determine whether a page runtime module exists in the registry.
 *
 * @param {string} key
 * @returns {boolean}
 */
export function hasPageModule(key) {
    return getPageModule(key) !== null;
}