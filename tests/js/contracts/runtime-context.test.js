/**
 * AuthKit
 * -----------------------------------------------------------------------------
 * File: runtime-context.test.js
 * Author: Michael Erastus
 * Package: AuthKit
 *
 * Description:
 * -----------------------------------------------------------------------------
 * Unit tests for AuthKit runtime-context contract helpers.
 *
 * Responsibilities:
 * - Verify valid runtime-context detection.
 * - Verify invalid context rejection.
 * - Verify descriptive assertion failures for invalid runtime contexts.
 */

import { describe, expect, it, vi } from 'vitest';

import {
    assertRuntimeContext,
    isRuntimeContext,
} from '../../../public/authkit/js/contracts/runtime-context.js';


describe('contracts/runtime-context', () => {
    it('recognizes a valid AuthKit runtime context', () => {
        const context = {
            root: document.documentElement,
            pageElement: null,
            page: {},
            config: {},
            moduleRegistry: {},
            pageRegistry: {},
            getRuntime: vi.fn(),
            getState: vi.fn(),
            emit: vi.fn(),
        };

        expect(isRuntimeContext(context)).toBe(true);
    });

    it('rejects invalid AuthKit runtime contexts', () => {
        expect(isRuntimeContext(null)).toBe(false);

        expect(isRuntimeContext({
            page: {},
            config: {},
            moduleRegistry: {},
            pageRegistry: {},
            getRuntime: vi.fn(),
            getState: vi.fn(),
        })).toBe(false);

        expect(isRuntimeContext({
            page: {},
            config: {},
            moduleRegistry: {},
            pageRegistry: {},
            getRuntime: 'invalid',
            getState: vi.fn(),
            emit: vi.fn(),
        })).toBe(false);
    });

    it('asserts valid runtime contexts and throws for invalid ones', () => {
        const context = {
            root: document.documentElement,
            pageElement: null,
            page: {},
            config: {},
            moduleRegistry: {},
            pageRegistry: {},
            getRuntime: vi.fn(),
            getState: vi.fn(),
            emit: vi.fn(),
        };

        expect(assertRuntimeContext(context)).toBe(context);

        expect(() => assertRuntimeContext({})).toThrow(
            'AuthKit runtime context must expose the required runtime context shape.'
        );
    });
});