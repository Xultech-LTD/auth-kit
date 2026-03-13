/**
 * AuthKit
 * -----------------------------------------------------------------------------
 * File: js/pages/index.js
 * Author: Michael Erastus
 * Package: AuthKit
 *
 * Description:
 * -----------------------------------------------------------------------------
 * Public page-module entry for the AuthKit browser runtime.
 *
 * This file is responsible for re-exporting all built-in page runtime modules
 * from a single location so internal runtime code, tests, and future extension
 * points can import page modules consistently.
 *
 * Responsibilities:
 * - Re-export each built-in page runtime module.
 * - Provide a small helper for resolving a flat built-in page module map.
 * - Keep page-module exports centralized and declarative.
 *
 * Design notes:
 * - This file does not boot page modules directly.
 * - This file does not contain page-specific business logic.
 * - Registry wiring remains the responsibility of registry/pages.js.
 *
 * @package   AuthKit
 * @author    Michael Erastus
 * @license   MIT
 */

import * as login from './login.js';
import * as register from './register.js';
import * as twoFactorChallenge from './two-factor-challenge.js';
import * as twoFactorRecovery from './two-factor-recovery.js';
import * as emailVerificationNotice from './email-verification-notice.js';
import * as emailVerificationToken from './email-verification-token.js';
import * as emailVerificationSuccess from './email-verification-success.js';
import * as passwordForgot from './password-forgot.js';
import * as passwordForgotSent from './password-forgot-sent.js';
import * as passwordReset from './password-reset.js';
import * as passwordResetToken from './password-reset-token.js';
import * as passwordResetSuccess from './password-reset-success.js';


/**
 * Resolve the flat built-in AuthKit page-module map.
 *
 * Keys align with:
 * - authkit.javascript.pages.{key}
 *
 * @returns {Record<string, Object>}
 */
export function getBuiltInPageModules() {
    return {
        login,
        register,
        two_factor_challenge: twoFactorChallenge,
        two_factor_recovery: twoFactorRecovery,
        email_verification_notice: emailVerificationNotice,
        email_verification_token: emailVerificationToken,
        email_verification_success: emailVerificationSuccess,
        password_forgot: passwordForgot,
        password_forgot_sent: passwordForgotSent,
        password_reset: passwordReset,
        password_reset_token: passwordResetToken,
        password_reset_success: passwordResetSuccess,
    };
}

export {
    login,
    register,
    twoFactorChallenge,
    twoFactorRecovery,
    emailVerificationNotice,
    emailVerificationToken,
    emailVerificationSuccess,
    passwordForgot,
    passwordForgotSent,
    passwordReset,
    passwordResetToken,
    passwordResetSuccess,
};