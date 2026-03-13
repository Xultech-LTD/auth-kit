/**
 * AuthKit
 * -----------------------------------------------------------------------------
 * File: tests/js/registry/modules.test.js
 * Author: Michael Erastus
 * Package: AuthKit
 *
 * Description:
 * -----------------------------------------------------------------------------
 * Unit tests for the AuthKit shared runtime module registry.
 *
 * Responsibilities:
 * - Verify the built-in shared module registry is populated.
 * - Verify expected built-in module keys are present.
 * - Verify registry lookup helpers resolve known modules correctly.
 * - Verify invalid module lookups fail safely.
 * - Verify registry access returns a shallow clone.
 */

import { describe, expect, it } from 'vitest';

import {
    getModule,
    getModuleRegistry,
    hasModule,
    moduleRegistry,
} from '../../../resources/js/authkit/registry/modules.js';


describe('registry/modules', () => {
    it('exposes the built-in shared runtime module registry', () => {
        expect(moduleRegistry).toBeDefined();
        expect(typeof moduleRegistry).toBe('object');
    });

    it('contains the expected built-in shared module keys', () => {
        expect(moduleRegistry).toHaveProperty('theme');
        expect(moduleRegistry).toHaveProperty('forms');
    });

    it('returns a shallow clone of the shared module registry', () => {
        const registry = getModuleRegistry();

        expect(registry).toEqual(moduleRegistry);
        expect(registry).not.toBe(moduleRegistry);
    });

    it('resolves a shared runtime module by key', () => {
        expect(getModule('theme')).toBe(moduleRegistry.theme);
        expect(getModule('forms')).toBe(moduleRegistry.forms);
    });

    it('fails safely for invalid shared runtime module keys', () => {
        expect(getModule('')).toBeNull();
        expect(getModule('missing')).toBeNull();
        expect(getModule(null)).toBeNull();
    });

    it('detects whether a shared runtime module exists', () => {
        expect(hasModule('theme')).toBe(true);
        expect(hasModule('forms')).toBe(true);

        expect(hasModule('missing')).toBe(false);
        expect(hasModule('')).toBe(false);
        expect(hasModule(null)).toBe(false);
    });
});