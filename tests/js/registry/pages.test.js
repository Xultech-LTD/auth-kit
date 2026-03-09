/**
 * AuthKit
 * -----------------------------------------------------------------------------
 * File: pages.test.js
 * Author: Michael Erastus
 * Package: AuthKit
 *
 * Description:
 * -----------------------------------------------------------------------------
 * Unit tests for the AuthKit page runtime module registry.
 *
 * Responsibilities:
 * - Verify the built-in page runtime registry is populated.
 * - Verify expected built-in page module keys are present.
 * - Verify page registry lookup helpers resolve known modules correctly.
 * - Verify invalid page-module lookups fail safely.
 * - Verify registry access returns a shallow clone.
 */

import { describe, expect, it } from 'vitest';

import {
    getPageModule,
    getPageRegistry,
    hasPageModule,
    pageRegistry,
} from '../../../public/authkit/js/registry/pages.js';


describe('registry/pages', () => {
    it('exposes the built-in page runtime registry', () => {
        expect(pageRegistry).toBeDefined();
        expect(typeof pageRegistry).toBe('object');
    });

    it('contains the expected built-in page module keys', () => {
        expect(pageRegistry).toHaveProperty('login');
        expect(pageRegistry).toHaveProperty('register');
        expect(pageRegistry).toHaveProperty('two_factor_challenge');
        expect(pageRegistry).toHaveProperty('two_factor_recovery');
        expect(pageRegistry).toHaveProperty('email_verification_notice');
        expect(pageRegistry).toHaveProperty('email_verification_token');
        expect(pageRegistry).toHaveProperty('email_verification_success');
        expect(pageRegistry).toHaveProperty('password_forgot');
        expect(pageRegistry).toHaveProperty('password_forgot_sent');
        expect(pageRegistry).toHaveProperty('password_reset');
        expect(pageRegistry).toHaveProperty('password_reset_token');
        expect(pageRegistry).toHaveProperty('password_reset_success');
    });

    it('returns a shallow clone of the page runtime registry', () => {
        const registry = getPageRegistry();

        expect(registry).toEqual(pageRegistry);
        expect(registry).not.toBe(pageRegistry);
    });

    it('resolves a page runtime module by key', () => {
        expect(getPageModule('login')).toBe(pageRegistry.login);
        expect(getPageModule('register')).toBe(pageRegistry.register);
        expect(getPageModule('password_reset')).toBe(pageRegistry.password_reset);
    });

    it('fails safely for invalid page runtime module keys', () => {
        expect(getPageModule('')).toBeNull();
        expect(getPageModule('missing')).toBeNull();
        expect(getPageModule(null)).toBeNull();
    });

    it('detects whether a page runtime module exists', () => {
        expect(hasPageModule('login')).toBe(true);
        expect(hasPageModule('register')).toBe(true);
        expect(hasPageModule('password_reset')).toBe(true);

        expect(hasPageModule('missing')).toBe(false);
        expect(hasPageModule('')).toBe(false);
        expect(hasPageModule(null)).toBe(false);
    });
});