/**
 * AuthKit
 * -----------------------------------------------------------------------------
 * File: tests/js/modules/forms/redirect.test.js
 * Author: Michael Erastus
 * Package: AuthKit
 *
 * Description:
 * -----------------------------------------------------------------------------
 * Unit tests for AuthKit AJAX form redirect helpers.
 *
 * Responsibilities:
 * - Verify redirect behavior resolution from runtime context.
 * - Verify fallback redirect resolution from runtime context.
 * - Verify support for both camelCase and snake_case config keys.
 * - Verify safe browser redirect execution.
 * - Verify redirect handling for normalized successful form results.
 */

import { beforeEach, describe, expect, it, vi } from 'vitest';

import {
    handleRedirect,
    performRedirect,
    resolveFallbackRedirect,
    resolveRedirectBehavior,
} from '../../../../resources/js/authkit/modules/forms/redirect.js';


describe('modules/forms/redirect', () => {
    beforeEach(() => {
        vi.restoreAllMocks();
    });

    it('resolves redirect behavior from top-level camelCase forms config first', () => {
        const context = {
            config: {
                forms: {
                    successBehavior: 'none',
                    success_behavior: 'redirect',
                    ajax: {
                        successBehavior: 'redirect',
                        success_behavior: 'redirect',
                    },
                },
            },
        };

        expect(resolveRedirectBehavior(context)).toBe('none');
    });

    it('resolves redirect behavior from top-level snake_case forms config when camelCase is absent', () => {
        const context = {
            config: {
                forms: {
                    success_behavior: 'none',
                    ajax: {
                        successBehavior: 'redirect',
                        success_behavior: 'redirect',
                    },
                },
            },
        };

        expect(resolveRedirectBehavior(context)).toBe('none');
    });

    it('falls back to ajax camelCase redirect behavior when top-level behavior is absent', () => {
        const context = {
            config: {
                forms: {
                    ajax: {
                        successBehavior: 'redirect',
                    },
                },
            },
        };

        expect(resolveRedirectBehavior(context)).toBe('redirect');
    });

    it('falls back to ajax snake_case redirect behavior when other behavior keys are absent', () => {
        const context = {
            config: {
                forms: {
                    ajax: {
                        success_behavior: 'none',
                    },
                },
            },
        };

        expect(resolveRedirectBehavior(context)).toBe('none');
    });

    it('falls back to redirect when redirect behavior is not configured', () => {
        expect(resolveRedirectBehavior({})).toBe('redirect');
        expect(resolveRedirectBehavior(null)).toBe('redirect');
    });

    it('resolves fallback redirect from top-level camelCase forms config first', () => {
        const context = {
            config: {
                forms: {
                    fallbackRedirect: '/dashboard',
                    fallback_redirect: '/snake-dashboard',
                    ajax: {
                        fallbackRedirect: '/login',
                        fallback_redirect: '/snake-login',
                    },
                },
            },
        };

        expect(resolveFallbackRedirect(context)).toBe('/dashboard');
    });

    it('resolves fallback redirect from top-level snake_case forms config when camelCase is absent', () => {
        const context = {
            config: {
                forms: {
                    fallback_redirect: '/dashboard',
                    ajax: {
                        fallbackRedirect: '/login',
                        fallback_redirect: '/snake-login',
                    },
                },
            },
        };

        expect(resolveFallbackRedirect(context)).toBe('/dashboard');
    });

    it('falls back to ajax camelCase fallback redirect when top-level fallback is absent', () => {
        const context = {
            config: {
                forms: {
                    ajax: {
                        fallbackRedirect: '/login',
                    },
                },
            },
        };

        expect(resolveFallbackRedirect(context)).toBe('/login');
    });

    it('falls back to ajax snake_case fallback redirect when other fallback keys are absent', () => {
        const context = {
            config: {
                forms: {
                    ajax: {
                        fallback_redirect: '/login',
                    },
                },
            },
        };

        expect(resolveFallbackRedirect(context)).toBe('/login');
    });

    it('falls back to an empty string when no fallback redirect is configured', () => {
        expect(resolveFallbackRedirect({})).toBe('');
        expect(resolveFallbackRedirect(null)).toBe('');
    });

    it('performs a browser redirect for a valid URL', () => {
        const assign = vi.fn();

        Object.defineProperty(window, 'location', {
            value: {
                assign,
            },
            writable: true,
            configurable: true,
        });

        expect(performRedirect('/dashboard')).toBe(true);
        expect(assign).toHaveBeenCalledTimes(1);
        expect(assign).toHaveBeenCalledWith('/dashboard');
    });

    it('does not redirect when the provided URL is empty', () => {
        const assign = vi.fn();

        Object.defineProperty(window, 'location', {
            value: {
                assign,
            },
            writable: true,
            configurable: true,
        });

        expect(performRedirect('')).toBe(false);
        expect(performRedirect(null)).toBe(false);
        expect(assign).not.toHaveBeenCalled();
    });

    it('uses the normalized success redirect URL when redirect behavior is enabled', () => {
        const assign = vi.fn();

        Object.defineProperty(window, 'location', {
            value: {
                assign,
            },
            writable: true,
            configurable: true,
        });

        const context = {
            config: {
                forms: {
                    success_behavior: 'redirect',
                    fallback_redirect: '/fallback',
                },
            },
        };

        const result = {
            ok: true,
            redirectUrl: '/email/verify?email=meritinfos%40gmail.com',
        };

        expect(handleRedirect(context, result)).toBe(true);
        expect(assign).toHaveBeenCalledTimes(1);
        expect(assign).toHaveBeenCalledWith('/email/verify?email=meritinfos%40gmail.com');
    });

    it('uses the configured fallback redirect when the normalized success result has no redirect URL', () => {
        const assign = vi.fn();

        Object.defineProperty(window, 'location', {
            value: {
                assign,
            },
            writable: true,
            configurable: true,
        });

        const context = {
            config: {
                forms: {
                    success_behavior: 'redirect',
                    fallback_redirect: '/dashboard',
                },
            },
        };

        const result = {
            ok: true,
            redirectUrl: null,
        };

        expect(handleRedirect(context, result)).toBe(true);
        expect(assign).toHaveBeenCalledTimes(1);
        expect(assign).toHaveBeenCalledWith('/dashboard');
    });

    it('does not redirect when redirect behavior is disabled', () => {
        const assign = vi.fn();

        Object.defineProperty(window, 'location', {
            value: {
                assign,
            },
            writable: true,
            configurable: true,
        });

        const context = {
            config: {
                forms: {
                    success_behavior: 'none',
                    fallback_redirect: '/dashboard',
                },
            },
        };

        const result = {
            ok: true,
            redirectUrl: '/account',
        };

        expect(handleRedirect(context, result)).toBe(false);
        expect(assign).not.toHaveBeenCalled();
    });

    it('does not redirect when neither result redirect nor fallback redirect is available', () => {
        const assign = vi.fn();

        Object.defineProperty(window, 'location', {
            value: {
                assign,
            },
            writable: true,
            configurable: true,
        });

        const context = {
            config: {
                forms: {
                    success_behavior: 'redirect',
                },
            },
        };

        const result = {
            ok: true,
            redirectUrl: null,
        };

        expect(handleRedirect(context, result)).toBe(false);
        expect(assign).not.toHaveBeenCalled();
    });
});
