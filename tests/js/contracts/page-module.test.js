/**
 * AuthKit
 * -----------------------------------------------------------------------------
 * File: tests/js/contracts/page-module.test.js
 * Author: Michael Erastus
 * Package: AuthKit
 *
 * Description:
 * -----------------------------------------------------------------------------
 * Unit tests for AuthKit page-module runtime contract helpers.
 *
 * Responsibilities:
 * - Verify valid page-module detection.
 * - Verify safe boot function resolution.
 * - Verify descriptive assertion failures for invalid page modules.
 */

import { describe, expect, it, vi } from 'vitest';

import {
    assertPageModule,
    getPageModuleBoot,
    isPageModule,
} from '../../../public/authkit/js/contracts/page-module.js';


describe('contracts/page-module', () => {
    it('recognizes valid AuthKit page modules', () => {
        const module = {
            boot: vi.fn(),
        };

        expect(isPageModule(module)).toBe(true);
    });

    it('rejects invalid AuthKit page modules', () => {
        expect(isPageModule(null)).toBe(false);
        expect(isPageModule({})).toBe(false);
        expect(isPageModule({ boot: 'not-a-function' })).toBe(false);
    });

    it('resolves the boot function safely from a valid page module', () => {
        const boot = vi.fn();
        const module = { boot };

        expect(getPageModuleBoot(module)).toBe(boot);
    });

    it('returns null when resolving boot from an invalid page module', () => {
        expect(getPageModuleBoot(null)).toBeNull();
        expect(getPageModuleBoot({})).toBeNull();
    });

    it('asserts valid page modules and throws for invalid ones', () => {
        const module = { boot: vi.fn() };

        expect(assertPageModule(module)).toBe(module);

        expect(() => assertPageModule({})).toThrow(
            'AuthKit page module must expose a boot(context) function.'
        );
    });
});