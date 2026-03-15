/**
 * AuthKit
 * -----------------------------------------------------------------------------
 * File: js/modules/forms/index.js
 * Author: Michael Erastus
 * Package: AuthKit
 *
 * Description:
 * -----------------------------------------------------------------------------
 * Public entry for the AuthKit forms runtime module.
 *
 * This file is responsible for exposing the forms module surface used by the
 * shared runtime registry while keeping the internal module files organized and
 * individually testable.
 *
 * Responsibilities:
 * - Export the forms module boot function used by the runtime registry.
 * - Boot both AJAX submission handling and native HTTP loading enhancement.
 * - Re-export core forms helpers for internal extension and testing.
 * - Provide a stable public surface for page modules that want to compose or
 *   extend the AuthKit forms runtime behavior.
 *
 * Design notes:
 * - The runtime registry expects this module to expose a `boot(context)`
 *   function.
 * - This file should remain lightweight and declarative.
 * - Business logic should stay in the dedicated form module files.
 * - AJAX form submission behavior remains owned by bind-forms.js.
 * - Native HTTP submit loading enhancement remains owned by native-loading.js.
 *
 * @package   AuthKit
 * @author    Michael Erastus
 * @license   MIT
 */

import { bootForms } from './bind-forms.js';
import { bootNativeLoading } from './native-loading.js';


/**
 * Boot the full AuthKit forms runtime.
 *
 * Responsibilities:
 * - Boot AJAX-managed forms.
 * - Boot native HTTP loading enhancement for non-AJAX forms.
 * - Return combined runtime metadata and a unified cleanup callback.
 *
 * Returned shape:
 * - ajax: boot result from bootForms()
 * - native: boot result from bootNativeLoading()
 * - forms: combined unique forms array
 * - count: total combined unique form count
 * - cleanup: removes both AJAX and native-loading bindings
 *
 * Notes:
 * - This function is exported as `boot` because the shared runtime registry
 *   expects every module entry to expose a boot(context) function.
 * - Keeping `boot` as the canonical export preserves compatibility with the
 *   existing module registry and runtime boot pipeline.
 *
 * @param {Object|null} context
 * @returns {{
 *   ajax: {forms: HTMLFormElement[], count: number, cleanup: Function},
 *   native: {forms: HTMLFormElement[], count: number, cleanup: Function},
 *   forms: HTMLFormElement[],
 *   count: number,
 *   cleanup: Function
 * }}
 */
export function boot(context) {
    const ajax = bootForms(context);
    const native = bootNativeLoading(context);

    const forms = Array.from(new Set([
        ...(Array.isArray(ajax?.forms) ? ajax.forms : []),
        ...(Array.isArray(native?.forms) ? native.forms : []),
    ]));

    return {
        ajax,
        native,
        forms,
        count: forms.length,
        cleanup() {
            if (typeof ajax?.cleanup === 'function') {
                ajax.cleanup();
            }

            if (typeof native?.cleanup === 'function') {
                native.cleanup();
            }
        },
    };
}

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
    getLoadingConfig,
    getSubmitButtons,
    getButtonLabelElement,
    getButtonLoaderElement,
    resolveButtonLoadingType,
    resolveButtonLoadingText,
    captureButtonState,
    applyButtonLoadingState,
    clearButtonLoadingState,
    applyLoadingState,
    clearLoadingState,
} from './loading.js';

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
    endSubmitState,
    createTransportErrorResult,
    submitForm,
} from './submit.js';

export {
    isFormElement,
    shouldPreventDoubleSubmit,
    isFormBound,
    getFormBinding,
    unbindForm,
    bindForm,
    bindForms,
    unbindForms,
    rebindForm,
    bootForms,
} from './bind-forms.js';

export {
    getAjaxAttribute,
    isAjaxManagedForm,
    shouldPreventDoubleSubmit as shouldPreventNativeDoubleSubmit,
    shouldBindNativeLoading,
    isNativeLoadingBound,
    getNativeLoadingBinding,
    unbindNativeLoadingForm,
    bindNativeLoadingForm,
    bindNativeLoadingForms,
    unbindNativeLoadingForms,
    rebindNativeLoadingForm,
    getNativeLoadingForms,
    bootNativeLoading,
} from './native-loading.js';