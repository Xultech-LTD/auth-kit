/**
 * AuthKit
 * -----------------------------------------------------------------------------
 * File: runtime.js
 * Author: Michael Erastus
 * Package: AuthKit
 *
 * Description:
 * -----------------------------------------------------------------------------
 * Central runtime bootstrap orchestration for the AuthKit browser client.
 *
 * This file is responsible for coordinating the AuthKit client lifecycle:
 * - preparing a normalized runtime context
 * - booting shared runtime modules
 * - booting the active page module
 * - dispatching high-level lifecycle events
 * - exposing a stable public runtime surface
 *
 * Responsibilities:
 * - Build the normalized runtime context used by modules and page scripts.
 * - Boot enabled shared modules such as theme and forms.
 * - Resolve and boot the active page module when present.
 * - Dispatch stable lifecycle browser events for consumer extensions.
 * - Expose runtime metadata and boot state for diagnostics.
 *
 * Design notes:
 * - This file does not contain page-specific business logic.
 * - This file does not contain theme-specific business logic.
 * - Runtime boot should be safe, idempotent, and resilient.
 * - Modules are booted progressively; failure in one module should not crash
 *   the entire runtime.
 * - The AuthKit global object must remain stable and must never be replaced
 *   after configuration has been attached to it.
 * - The public runtime API is therefore exposed under:
 *   window[windowKey].runtime
 *   while preserving:
 *   window[windowKey].config
 *
 * @package   AuthKit
 * @author    Michael Erastus
 * @license   MIT
 */

import { getWindowKey } from './config.js';
import { dispatchEvent } from './events.js';
import { getDocumentRoot, getPageElement } from './dom.js';
import {dataGet, isFunction, isObject, isString, isUndefined} from './helpers.js';
import { getPageContext } from './page.js';


/**
 * Internal AuthKit runtime state.
 *
 * @type {{
 *   booted: boolean,
 *   booting: boolean,
 *   context: Object|null,
 *   modules: Record<string, Object>,
 *   page: Object|null,
 *   errors: Array<Object>
 * }}
 */
const runtimeState = {
    booted: false,
    booting: false,
    context: null,
    modules: {},
    page: null,
    errors: [],
};


/**
 * Create the normalized runtime context passed to modules and page scripts.
 *
 * @param {Object} dependencies
 * @param {Object} dependencies.config
 * @param {Object} dependencies.moduleRegistry
 * @param {Object} dependencies.pageRegistry
 * @returns {Object}
 */
export function createRuntimeContext({
                                         config,
                                         moduleRegistry,
                                         pageRegistry,
                                     }) {
    const page = getPageContext();
    const root = getDocumentRoot();
    const pageElement = getPageElement();

    return {
        bootedAt: null,
        root,
        pageElement,
        page,
        config,
        moduleRegistry,
        pageRegistry,

        /**
         * Access the public runtime API.
         *
         * @returns {Object|null}
         */
        getRuntime() {
            return getRuntimeApi();
        },

        /**
         * Access the current runtime state snapshot.
         *
         * @returns {Object}
         */
        getState() {
            return getRuntimeState();
        },

        /**
         * Dispatch a configured AuthKit runtime event.
         *
         * @param {string} eventKey
         * @param {Object} [detail={}]
         * @returns {CustomEvent|null}
         */
        emit(eventKey, detail = {}) {
            return dispatchEvent(eventKey, detail);
        },
    };
}


/**
 * Get a cloned snapshot of internal runtime state.
 *
 * @returns {Object}
 */
export function getRuntimeState() {
    return {
        booted: runtimeState.booted,
        booting: runtimeState.booting,
        context: runtimeState.context,
        modules: { ...runtimeState.modules },
        page: runtimeState.page,
        errors: [...runtimeState.errors],
    };
}


/**
 * Determine whether the AuthKit runtime has already booted.
 *
 * @returns {boolean}
 */
export function hasBooted() {
    return runtimeState.booted;
}


/**
 * Determine whether the AuthKit runtime is currently booting.
 *
 * @returns {boolean}
 */
export function isRuntimeBooting() {
    return runtimeState.booting;
}


/**
 * Store a runtime error record.
 *
 * @param {string} scope
 * @param {string} key
 * @param {*} error
 * @returns {void}
 */
export function recordRuntimeError(scope, key, error) {
    runtimeState.errors.push({
        scope,
        key,
        error,
        timestamp: Date.now(),
    });
}


/**
 * Ensure the root AuthKit global namespace object exists.
 *
 * This helper preserves any previously attached configuration or metadata and
 * guarantees that the runtime API can be exposed without replacing the global
 * object entirely.
 *
 * Expected stable global shape:
 * - window[windowKey] = {
 *     config: { ... },
 *     runtime: { ... }
 *   }
 *
 * @returns {Object|null}
 */
export function ensureGlobalNamespace() {
    if (isUndefined(window)) {
        return null;
    }

    const windowKey = getWindowKey();

    if (!isString(windowKey) || windowKey.trim() === '') {
        return null;
    }

    if (!isObject(window[windowKey])) {
        window[windowKey] = {};
    }

    return window[windowKey];
}


/**
 * Boot a single shared runtime module from the provided module registry.
 *
 * Expected module contract:
 * - module.boot(context) => any
 *
 * @param {string} key
 * @param {Object} context
 * @param {Object} moduleRegistry
 * @returns {Object|null}
 */
export function bootModule(key, context, moduleRegistry) {
    const definition = moduleRegistry?.[key] ?? null;

    if (!isObject(definition)) {
        return null;
    }

    const boot = definition.boot;

    if (!isFunction(boot)) {
        return null;
    }

    try {
        const result = boot(context);

        const moduleState = {
            key,
            booted: true,
            result: result ?? null,
            error: null,
        };

        runtimeState.modules[key] = moduleState;

        return moduleState;
    } catch (error) {
        recordRuntimeError('module', key, error);

        const moduleState = {
            key,
            booted: false,
            result: null,
            error,
        };

        runtimeState.modules[key] = moduleState;

        return moduleState;
    }
}


/**
 * Boot all shared runtime modules declared by configuration.
 *
 * @param {Object} context
 * @param {Object} moduleRegistry
 * @returns {Record<string, Object>}
 */
export function bootModules(context, moduleRegistry) {
    const configuredModules = dataGet(context.config, 'modules', {});
    const entries = isObject(configuredModules) ? Object.entries(configuredModules) : [];

    for (const [key, moduleConfig] of entries) {
        const enabled = Boolean(dataGet(moduleConfig, 'enabled', false));

        if (!enabled) {
            continue;
        }

        if (!isObject(moduleRegistry?.[key])) {
            continue;
        }

        bootModule(key, context, moduleRegistry);
    }

    return { ...runtimeState.modules };
}


/**
 * Boot the active page module when one is resolved.
 *
 * Expected page module contract:
 * - pageModule.boot(context) => any
 *
 * @param {Object} context
 * @param {Object} pageRegistry
 * @returns {Object|null}
 */
export function bootPage(context, pageRegistry) {
    const page = context.page;

    if (!isObject(page) || !page.key) {
        runtimeState.page = null;
        return null;
    }

    const definition = pageRegistry?.[page.key] ?? null;

    if (!isObject(definition) || !isFunction(definition.boot)) {
        runtimeState.page = {
            key: page.key,
            pageKey: page.pageKey ?? null,
            booted: false,
            result: null,
            error: null,
        };

        return runtimeState.page;
    }

    try {
        const result = definition.boot(context);

        runtimeState.page = {
            key: page.key,
            pageKey: page.pageKey ?? null,
            booted: true,
            result: result ?? null,
            error: null,
        };

        context.emit('page_ready', {
            pageKey: page.pageKey ?? null,
            pageModuleKey: page.key,
        });

        return runtimeState.page;
    } catch (error) {
        recordRuntimeError('page', page.key, error);

        runtimeState.page = {
            key: page.key,
            pageKey: page.pageKey ?? null,
            booted: false,
            result: null,
            error,
        };

        return runtimeState.page;
    }
}


/**
 * Build the public runtime API surface.
 *
 * This API is exposed under:
 * - window[windowKey].runtime
 *
 * The root AuthKit global object itself is preserved so that:
 * - window[windowKey].config
 * remains intact.
 *
 * @returns {Object}
 */
export function getRuntimeApi() {
    return {
        /**
         * Determine whether the runtime has completed boot.
         *
         * @returns {boolean}
         */
        isBooted() {
            return hasBooted();
        },

        /**
         * Determine whether the runtime is currently booting.
         *
         * @returns {boolean}
         */
        isBooting() {
            return isRuntimeBooting();
        },

        /**
         * Return a current runtime state snapshot.
         *
         * @returns {Object}
         */
        state() {
            return getRuntimeState();
        },

        /**
         * Dispatch a configured AuthKit event manually.
         *
         * @param {string} eventKey
         * @param {Object} [detail={}]
         * @returns {CustomEvent|null}
         */
        emit(eventKey, detail = {}) {
            return dispatchEvent(eventKey, detail);
        },
    };
}


/**
 * Expose the public runtime API on the browser AuthKit namespace.
 *
 * This method preserves the root global object and assigns the public runtime
 * API to:
 * - window[windowKey].runtime
 *
 * Example final shape:
 * - window.AuthKit.config
 * - window.AuthKit.runtime
 *
 * @returns {Object|null}
 */
export function exposeRuntimeApi() {
    if (isUndefined(window)) {
        return null;
    }

    const globalNamespace = ensureGlobalNamespace();

    if (!globalNamespace) {
        return null;
    }

    const runtimeApi = getRuntimeApi();

    globalNamespace.runtime = runtimeApi;

    return runtimeApi;
}


/**
 * Boot the full AuthKit client runtime.
 *
 * This function is intentionally idempotent:
 * - if already booted, the existing state is returned
 * - if currently booting, the current state snapshot is returned
 *
 * @param {Object} dependencies
 * @param {Object} dependencies.config
 * @param {Object} dependencies.moduleRegistry
 * @param {Object} dependencies.pageRegistry
 * @returns {Object}
 */
export function bootRuntime({
                                config,
                                moduleRegistry,
                                pageRegistry,
                            }) {
    if (runtimeState.booted) {
        return getRuntimeState();
    }

    if (runtimeState.booting) {
        return getRuntimeState();
    }

    runtimeState.booting = true;

    const context = createRuntimeContext({
        config,
        moduleRegistry,
        pageRegistry,
    });

    runtimeState.context = context;

    exposeRuntimeApi();
    bootModules(context, moduleRegistry);
    bootPage(context, pageRegistry);

    runtimeState.booted = true;
    runtimeState.booting = false;
    runtimeState.context = {
        ...context,
        bootedAt: Date.now(),
    };

    dispatchEvent('ready', {
        pageKey: dataGet(runtimeState.context, 'page.pageKey', null),
        pageModuleKey: dataGet(runtimeState.context, 'page.key', null),
        modules: Object.keys(runtimeState.modules),
    });

    return getRuntimeState();
}