/**
 * AuthKit
 * -----------------------------------------------------------------------------
 * File: js/modules/app-shell/index.js
 * Author: Michael Erastus
 * Package: AuthKit
 */

import {
    getAttribute,
    listen,
    queryAll,
    queryOne,
} from '../../core/dom.js';
import {
    dataGet,
    isFunction,
    isObject,
    normalizeString,
} from '../../core/helpers.js';
import { createStorage } from '../../core/storage.js';


/**
 * Resolve the main AuthKit app shell element.
 *
 * @returns {HTMLElement|null}
 */
export function getAppShellElement() {
    const shell = queryOne('[data-authkit-shell="1"]');

    return shell instanceof HTMLElement ? shell : null;
}


/**
 * Resolve the configured app-shell module config.
 *
 * @param {Object|null} context
 * @returns {{
 *   enabled: boolean,
 *   allowCollapse: boolean,
 *   allowMobileDrawer: boolean,
 *   defaultCollapsed: boolean,
 *   storageKey: string,
 *   mobileBreakpoint: number
 * }}
 */
export function getAppShellConfig(context = null) {
    const config = isObject(context?.config) ? context.config : {};
    const moduleConfig = dataGet(config, 'modules.appShell', {});

    return {
        enabled: Boolean(dataGet(moduleConfig, 'enabled', false)),
        allowCollapse: Boolean(dataGet(moduleConfig, 'allowCollapse', true)),
        allowMobileDrawer: Boolean(dataGet(moduleConfig, 'allowMobileDrawer', true)),
        defaultCollapsed: Boolean(dataGet(moduleConfig, 'defaultCollapsed', false)),
        storageKey: normalizeString(
            dataGet(moduleConfig, 'storageKey', 'authkit.app.sidebar.collapsed'),
            'authkit.app.sidebar.collapsed'
        ),
        mobileBreakpoint: Number(dataGet(moduleConfig, 'mobileBreakpoint', 1024)) || 1024,
    };
}


/**
 * Determine whether the current viewport should be treated as mobile.
 *
 * @param {MediaQueryList|null} mediaQueryList
 * @returns {boolean}
 */
export function isMobileViewport(mediaQueryList) {
    return Boolean(mediaQueryList?.matches);
}


/**
 * Read the shell collapsed state.
 *
 * @param {HTMLElement|null} shell
 * @returns {boolean}
 */
export function isSidebarCollapsed(shell) {
    return getAttribute(shell, 'data-authkit-sidebar-collapsed', 'false') === 'true';
}


/**
 * Read the shell open state.
 *
 * @param {HTMLElement|null} shell
 * @returns {boolean}
 */
export function isSidebarOpen(shell) {
    return getAttribute(shell, 'data-authkit-sidebar-open', 'false') === 'true';
}


/**
 * Set desktop collapsed state on the shell.
 *
 * @param {HTMLElement|null} shell
 * @param {boolean} value
 * @returns {void}
 */
export function setSidebarCollapsed(shell, value) {
    if (!(shell instanceof HTMLElement)) {
        return;
    }

    shell.setAttribute('data-authkit-sidebar-collapsed', value ? 'true' : 'false');
}


/**
 * Set mobile drawer open state on the shell.
 *
 * @param {HTMLElement|null} shell
 * @param {boolean} value
 * @returns {void}
 */
export function setSidebarOpen(shell, value) {
    if (!(shell instanceof HTMLElement)) {
        return;
    }

    shell.setAttribute('data-authkit-sidebar-open', value ? 'true' : 'false');
}


/**
 * Apply body scroll locking for the mobile drawer.
 *
 * @param {boolean} locked
 * @returns {void}
 */
export function setBodyScrollLocked(locked) {
    if (!(document.body instanceof HTMLBodyElement)) {
        return;
    }

    document.body.style.overflow = locked ? 'hidden' : '';
}


/**
 * Persist desktop collapsed state.
 *
 * @param {ReturnType<typeof createStorage>} storage
 * @param {string} storageKey
 * @param {boolean} collapsed
 * @returns {void}
 */
export function persistCollapsedState(storage, storageKey, collapsed) {
    if (!storage || !isFunction(storage.set)) {
        return;
    }

    storage.set(storageKey, collapsed ? 'true' : 'false');
}


/**
 * Restore desktop collapsed state from storage.
 *
 * @param {ReturnType<typeof createStorage>} storage
 * @param {string} storageKey
 * @param {boolean} fallback
 * @returns {boolean}
 */
export function resolvePersistedCollapsedState(storage, storageKey, fallback = false) {
    if (!storage || !isFunction(storage.get)) {
        return fallback;
    }

    const stored = storage.get(storageKey, null);

    if (stored === 'true') {
        return true;
    }

    if (stored === 'false') {
        return false;
    }

    return fallback;
}


/**
 * Resolve the shell trigger elements.
 *
 * @param {HTMLElement|null} shell
 * @returns {{
 *   collapseTriggers: HTMLElement[],
 *   openTriggers: HTMLElement[],
 *   closeTriggers: HTMLElement[],
 *   backdrop: HTMLElement|null
 * }}
 */
export function getShellControls(shell) {
    return {
        collapseTriggers: queryAll('[data-authkit-sidebar-collapse-trigger]', shell).filter(
            (element) => element instanceof HTMLElement
        ),
        openTriggers: queryAll('[data-authkit-sidebar-open-trigger]', shell).filter(
            (element) => element instanceof HTMLElement
        ),
        closeTriggers: queryAll('[data-authkit-sidebar-close-trigger]', shell).filter(
            (element) => element instanceof HTMLElement
        ),
        backdrop: queryOne('[data-authkit-sidebar-backdrop]', shell),
    };
}


/**
 * Sync toggle button aria state for a nav item.
 *
 * @param {HTMLElement|null} item
 * @returns {void}
 */
export function syncNavToggleAria(item) {
    if (!(item instanceof HTMLElement)) {
        return;
    }

    const toggle = queryOne('[data-authkit-app-nav-toggle]', item);

    if (!(toggle instanceof HTMLButtonElement)) {
        return;
    }

    const expanded = getAttribute(item, 'data-authkit-app-nav-expanded', 'false') === 'true';
    toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
}


/**
 * Set nav item expanded state.
 *
 * @param {HTMLElement|null} item
 * @param {boolean} expanded
 * @returns {void}
 */
export function setNavItemExpanded(item, expanded) {
    if (!(item instanceof HTMLElement)) {
        return;
    }

    item.setAttribute('data-authkit-app-nav-expanded', expanded ? 'true' : 'false');
    syncNavToggleAria(item);
}


/**
 * Close sibling nav groups at the same level.
 *
 * @param {HTMLElement|null} item
 * @returns {void}
 */
export function closeSiblingNavItems(item) {
    if (!(item instanceof HTMLElement) || !(item.parentElement instanceof HTMLElement)) {
        return;
    }

    const siblings = Array.from(item.parentElement.children).filter(
        (node) => node instanceof HTMLElement && node !== item
    );

    siblings.forEach((sibling) => {
        const siblingItem = queryOne('[data-authkit-app-nav-item]', sibling) || sibling;

        if (siblingItem instanceof HTMLElement) {
            if (getAttribute(siblingItem, 'data-authkit-app-nav-has-children', 'false') === 'true') {
                setNavItemExpanded(siblingItem, false);
            }
        }
    });
}


/**
 * Bind child navigation toggles.
 *
 * Expected hooks:
 * - [data-authkit-app-nav-toggle]
 * - nearest [data-authkit-app-nav-item]
 *
 * @param {HTMLElement|null} shell
 * @returns {Function}
 */
export function bindNavToggles(shell) {
    const toggles = queryAll('[data-authkit-app-nav-toggle]', shell).filter(
        (element) => element instanceof HTMLButtonElement
    );

    const cleanupFns = [];

    toggles.forEach((toggle) => {
        const item = toggle.closest('[data-authkit-app-nav-item]');

        if (item instanceof HTMLElement) {
            syncNavToggleAria(item);
        }

        cleanupFns.push(
            listen(toggle, 'click', (event) => {
                event.preventDefault();
                event.stopPropagation();

                const item = toggle.closest('[data-authkit-app-nav-item]');

                if (!(item instanceof HTMLElement)) {
                    return;
                }

                const shellElement = toggle.closest('[data-authkit-shell="1"]');

                if (shellElement instanceof HTMLElement && isSidebarCollapsed(shellElement)) {
                    return;
                }

                const expanded = getAttribute(item, 'data-authkit-app-nav-expanded', 'false') === 'true';
                const next = !expanded;

                if (next) {
                    closeSiblingNavItems(item);
                }

                setNavItemExpanded(item, next);
            })
        );
    });

    const parentLinks = queryAll('.authkit-app-nav-item[data-authkit-app-nav-has-children="true"] > .authkit-app-nav-item__row > .authkit-app-nav-item__link', shell);

    parentLinks.forEach((link) => {
        cleanupFns.push(
            listen(link, 'click', (event) => {
                const href = link.getAttribute('href') || '';

                if (href !== '#') {
                    return;
                }

                const item = link.closest('[data-authkit-app-nav-item]');

                if (!(item instanceof HTMLElement)) {
                    return;
                }

                const shellElement = link.closest('[data-authkit-shell="1"]');

                if (shellElement instanceof HTMLElement && isSidebarCollapsed(shellElement)) {
                    return;
                }

                event.preventDefault();

                const expanded = getAttribute(item, 'data-authkit-app-nav-expanded', 'false') === 'true';
                const next = !expanded;

                if (next) {
                    closeSiblingNavItems(item);
                }

                setNavItemExpanded(item, next);
            })
        );
    });

    return () => {
        cleanupFns.forEach((cleanup) => {
            if (isFunction(cleanup)) {
                cleanup();
            }
        });
    };
}


/**
 * Boot the AuthKit app-shell runtime module.
 *
 * @param {Object|null} context
 * @returns {{
 *   shell: HTMLElement|null,
 *   cleanup: Function,
 *   config: Object|null
 * }}
 */
export function boot(context = null) {
    const shell = getAppShellElement();

    if (!(shell instanceof HTMLElement)) {
        return {
            shell: null,
            cleanup() {},
            config: null,
        };
    }

    const config = getAppShellConfig(context);

    if (config.enabled !== true) {
        return {
            shell,
            cleanup() {},
            config,
        };
    }

    if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') {
        return {
            shell,
            cleanup() {},
            config,
        };
    }

    const storage = createStorage('local');
    const mediaQuery = window.matchMedia(`(max-width: ${config.mobileBreakpoint - 0.02}px)`);
    const controls = getShellControls(shell);

    const cleanups = [];

    const applyDesktopCollapsedState = (collapsed) => {
        setSidebarCollapsed(shell, collapsed);
        persistCollapsedState(storage, config.storageKey, collapsed);
    };

    const closeMobileSidebar = () => {
        setSidebarOpen(shell, false);
        setBodyScrollLocked(false);
    };

    const openMobileSidebar = () => {
        setSidebarOpen(shell, true);
        setBodyScrollLocked(true);
    };

    const syncResponsiveState = () => {
        if (isMobileViewport(mediaQuery)) {
            closeMobileSidebar();
            return;
        }

        closeMobileSidebar();

        if (config.allowCollapse) {
            const persisted = resolvePersistedCollapsedState(
                storage,
                config.storageKey,
                config.defaultCollapsed
            );

            setSidebarCollapsed(shell, persisted);
        } else {
            setSidebarCollapsed(shell, false);
        }
    };

    if (config.allowCollapse) {
        controls.collapseTriggers.forEach((trigger) => {
            cleanups.push(
                listen(trigger, 'click', () => {
                    if (isMobileViewport(mediaQuery)) {
                        return;
                    }

                    applyDesktopCollapsedState(!isSidebarCollapsed(shell));
                })
            );
        });
    }

    if (config.allowMobileDrawer) {
        controls.openTriggers.forEach((trigger) => {
            cleanups.push(
                listen(trigger, 'click', () => {
                    if (!isMobileViewport(mediaQuery)) {
                        return;
                    }

                    if (isSidebarOpen(shell)) {
                        closeMobileSidebar();
                        return;
                    }

                    openMobileSidebar();
                })
            );
        });

        controls.closeTriggers.forEach((trigger) => {
            cleanups.push(
                listen(trigger, 'click', () => {
                    closeMobileSidebar();
                })
            );
        });

        if (controls.backdrop instanceof HTMLElement) {
            cleanups.push(
                listen(controls.backdrop, 'click', () => {
                    closeMobileSidebar();
                })
            );
        }

        cleanups.push(
            listen(document, 'keydown', (event) => {
                if (event.key !== 'Escape') {
                    return;
                }

                closeMobileSidebar();
            })
        );
    }

    if (typeof mediaQuery.addEventListener === 'function') {
        const handler = () => {
            syncResponsiveState();
        };

        mediaQuery.addEventListener('change', handler);

        cleanups.push(() => {
            mediaQuery.removeEventListener('change', handler);
        });
    } else if (typeof mediaQuery.addListener === 'function') {
        const handler = () => {
            syncResponsiveState();
        };

        mediaQuery.addListener(handler);

        cleanups.push(() => {
            mediaQuery.removeListener(handler);
        });
    }

    cleanups.push(bindNavToggles(shell));

    syncResponsiveState();

    return {
        shell,
        config,
        cleanup() {
            cleanups.forEach((cleanup) => {
                if (isFunction(cleanup)) {
                    cleanup();
                }
            });

            setBodyScrollLocked(false);
        },
    };
}