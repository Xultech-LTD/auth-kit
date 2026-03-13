/**
 * AuthKit
 * -----------------------------------------------------------------------------
 * File: tests/js/core/dom.test.js
 * Author: Michael Erastus
 * Package: AuthKit
 *
 * Description:
 * -----------------------------------------------------------------------------
 * Unit tests for AuthKit DOM utility helpers.
 *
 * Responsibilities:
 * - Verify DOM root and body resolution.
 * - Verify selector query helpers.
 * - Verify data attribute and normal attribute helpers.
 * - Verify page element/key helpers.
 * - Verify toggle and form discovery helpers.
 * - Verify class, listener, and DOM ready helpers.
 */

import { beforeEach, describe, expect, it, vi } from 'vitest';

import {
    addClass,
    closest,
    getAjaxForms,
    getAttribute,
    getBody,
    getDataAttribute,
    getDocumentRoot,
    getForms,
    getPageElement,
    getPageKey,
    getThemeToggleCycleButtons,
    getThemeToggleOptions,
    getThemeToggleSelects,
    hasDataAttribute,
    isDomReady,
    isElement,
    isPage,
    listen,
    onDomReady,
    queryAll,
    queryOne,
    removeClass,
    setDataAttribute,
} from '../../../public/authkit/js/core/dom.js';

import { resetCoreTestEnvironment } from './support/core-test-helpers.js';


describe('core/dom', () => {
    beforeEach(() => {
        resetCoreTestEnvironment();

        document.body.innerHTML = `
            <main class="authkit-page" data-authkit-page="login">
                <form id="form-a" data-authkit-ajax="1"></form>
                <form id="form-b"></form>

                <button data-authkit-theme-toggle="light"></button>
                <button data-authkit-theme-toggle="dark"></button>

                <select data-authkit-theme-toggle-select="1"></select>
                <button data-authkit-theme-toggle-cycle="1"></button>

                <div class="wrapper">
                    <span id="child" data-test-value="hello"></span>
                </div>
            </main>
        `;
    });

    it('resolves document root and body', () => {
        expect(getDocumentRoot()).toBe(document.documentElement);
        expect(getBody()).toBe(document.body);
    });

    it('queries elements safely', () => {
        expect(queryOne('.authkit-page')).not.toBeNull();
        expect(queryAll('form')).toHaveLength(2);
        expect(queryOne('')).toBeNull();
        expect(queryAll('')).toEqual([]);
    });

    it('detects valid DOM elements', () => {
        const element = document.getElementById('child');

        expect(isElement(element)).toBe(true);
        expect(isElement(null)).toBe(false);
    });

    it('reads and writes data attributes safely', () => {
        const child = document.getElementById('child');

        expect(getDataAttribute(child, 'data-test-value')).toBe('hello');

        setDataAttribute(child, 'data-authkit-mode', 'dark');

        expect(getDataAttribute(child, 'data-authkit-mode')).toBe('dark');
        expect(hasDataAttribute(child, 'data-authkit-mode')).toBe(true);
    });

    it('reads normal attributes safely', () => {
        const child = document.getElementById('child');

        expect(getAttribute(child, 'id')).toBe('child');
        expect(getAttribute(child, 'missing', 'fallback')).toBe('fallback');
    });

    it('resolves the current page element and key', () => {
        expect(getPageElement()).not.toBeNull();
        expect(getPageKey()).toBe('login');
        expect(isPage('login')).toBe(true);
        expect(isPage('register')).toBe(false);
    });

    it('discovers packaged theme toggle controls', () => {
        expect(getThemeToggleOptions()).toHaveLength(2);
        expect(getThemeToggleSelects()).toHaveLength(1);
        expect(getThemeToggleCycleButtons()).toHaveLength(1);
    });

    it('discovers ajax forms and general forms', () => {
        expect(getAjaxForms()).toHaveLength(1);
        expect(getForms()).toHaveLength(2);
    });

    it('resolves closest matching ancestors', () => {
        const child = document.getElementById('child');

        expect(closest(child, '.wrapper')).not.toBeNull();
        expect(closest(child, '.missing')).toBeNull();
    });

    it('adds and removes CSS classes safely', () => {
        const child = document.getElementById('child');

        addClass(child, ['one', 'two']);
        expect(child.classList.contains('one')).toBe(true);
        expect(child.classList.contains('two')).toBe(true);

        removeClass(child, 'one');
        expect(child.classList.contains('one')).toBe(false);
    });

    it('attaches listeners and returns cleanup callbacks', () => {
        const button = document.querySelector('[data-authkit-theme-toggle="light"]');
        const handler = vi.fn();

        const cleanup = listen(button, 'click', handler);

        button.click();
        expect(handler).toHaveBeenCalledTimes(1);

        cleanup();
        button.click();
        expect(handler).toHaveBeenCalledTimes(1);
    });

    it('reports DOM ready status and executes onDomReady callbacks', () => {
        expect(typeof isDomReady()).toBe('boolean');

        const callback = vi.fn();

        onDomReady(callback);

        expect(callback).toHaveBeenCalledTimes(1);
    });
});