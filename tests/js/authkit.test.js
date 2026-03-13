/**
 * AuthKit
 * -----------------------------------------------------------------------------
 * File: tests/js/authkit.test.js
 * Author: Michael Erastus
 * Package: AuthKit
 *
 * Description:
 * -----------------------------------------------------------------------------
 * Unit tests for the AuthKit public browser entrypoint.
 *
 * Responsibilities:
 * - Verify the public entry exports the expected runtime helpers.
 * - Verify the AuthKit runtime boots safely.
 * - Verify repeated boot calls remain safe and idempotent.
 * - Verify runtime state can be read through the public entry helpers.
 *
 * Design notes:
 * - These tests exercise the public entrypoint surface rather than page-level
 *   behavior.
 * - The runtime core already handles idempotent boot behavior, so this suite
 *   verifies the entrypoint integrates with that contract correctly.
 */

import { describe, expect, it } from 'vitest';

import {
    bootAuthKit,
    getAuthKitState,
    isAuthKitBooted,
} from '../../resources/js/authkit/authkit.js';


describe('authkit', () => {
    it('exports the expected public runtime helpers', () => {
        expect(typeof bootAuthKit).toBe('function');
        expect(typeof getAuthKitState).toBe('function');
        expect(typeof isAuthKitBooted).toBe('function');
    });

    it('boots the AuthKit runtime safely', () => {
        const state = bootAuthKit();

        expect(state).toBeDefined();
        expect(typeof state).toBe('object');
        expect(state.booted).toBe(true);
        expect(state.booting).toBe(false);
    });

    it('marks the AuthKit runtime as booted after boot', () => {
        bootAuthKit();

        expect(isAuthKitBooted()).toBe(true);
    });

    it('returns a readable runtime state snapshot through the public helper', () => {
        bootAuthKit();

        const state = getAuthKitState();

        expect(state).toBeDefined();
        expect(typeof state).toBe('object');
        expect(state.booted).toBe(true);
        expect(state.booting).toBe(false);
        expect(state).toHaveProperty('modules');
        expect(state).toHaveProperty('errors');
    });

    it('supports repeated boot calls safely and idempotently', () => {
        const firstState = bootAuthKit();
        const secondState = bootAuthKit();

        expect(firstState.booted).toBe(true);
        expect(secondState.booted).toBe(true);
        expect(secondState.booting).toBe(false);
    });
});