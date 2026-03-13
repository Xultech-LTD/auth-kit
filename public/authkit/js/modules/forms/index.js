/**
 * AuthKit
 * -----------------------------------------------------------------------------
 * File: js/modules/forms/index.js
 * Author: Michael Erastus
 * Package: AuthKit
 *
 * Description:
 * -----------------------------------------------------------------------------
 * Public entry for the AuthKit AJAX forms runtime module.
 *
 * This file is responsible for exposing the forms module surface used by the
 * shared runtime registry while keeping the internal module files organized and
 * individually testable.
 *
 * Responsibilities:
 * - Export the forms module boot function used by the runtime registry.
 * - Re-export core forms helpers for internal extension and testing.
 * - Provide a stable public surface for page modules that want to compose or
 *   extend the AuthKit forms runtime behavior.
 *
 * Design notes:
 * - The runtime registry expects this module to expose a `boot(context)`
 *   function.
 * - This file should remain lightweight and declarative.
 * - Business logic should stay in the dedicated form module files.
 *
 * @package   AuthKit
 * @author    Michael Erastus
 * @license   MIT
 */

export { bootForms as boot } from './bind-forms.js';

export {
    createFormState,
    isFormState,
    isSubmitting,
    setSubmitting,
    setLastResult,
    getLastResult,
    setFieldErrors,
    getFieldErrors,
    hasFieldErrors,
    clearFieldErrors,
    setMessage,
    getMessage,
    clearMessage,
    setMeta,
    getMeta,
    clearFeedbackState,
    resetFormState,
    normalizeFieldErrors as normalizeStateFieldErrors,
    cloneFieldErrors,
} from './state.js';

export {
    getFormMethod,
    getFormAction,
    isFormElement as isSerializedFormElement,
    serializeFormToFormData,
    serializeForm,
    formDataToObject,
    buildSerializedForm,
    mergeSerializedData,
    getSerializedValue,
    hasSerializedValue,
    getCurrentUrl,
} from './serialize.js';

export {
    normalizeSuccessResult,
    applySuccessState,
    handleSuccess,
} from './handle-success.js';

export {
    normalizeFieldErrors as normalizeErrorFieldErrors,
    resolveErrorMessage,
    normalizeErrorResult,
    applyErrorState,
    handleError,
} from './handle-error.js';

export {
    shouldSubmitAsJson,
    getSubmitSerialization,
    resolveSubmitUrl,
    resolveSubmitMethod,
    resolveSubmitHeaders,
    resolveSubmitTransport,
    resolveSubmitBody,
    buildSubmitRequest,
    createBeforeSubmitDetail,
    emitBeforeSubmit,
    beginSubmitState,
    createTransportErrorResult,
    submitForm,
} from './submit.js';

export {
    isFormElement,
    isFormBound,
    getFormBinding,
    unbindForm,
    bindForm,
    bindForms,
    unbindForms,
    rebindForm,
    bootForms,
} from './bind-forms.js';