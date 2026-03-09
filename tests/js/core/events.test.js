/**
 * AuthKit
 * -----------------------------------------------------------------------------
 * File: events.test.js
 * Author: Michael Erastus
 * Package: AuthKit
 *
 * Description:
 * -----------------------------------------------------------------------------
 * Unit tests for AuthKit browser event utilities.
 *
 * Responsibilities:
 * - Verify event name resolution.
 * - Verify configured event target resolution.
 * - Verify event detail payload creation.
 * - Verify CustomEvent dispatch behavior.
 * - Verify event listener helper APIs.
 */

import { beforeEach, describe, expect, it, vi } from 'vitest';

import {
    createEventDetail,
    dispatchEvent,
    getDefaultEventNames,
    getEventName,
    getEventTarget,
    getEventTargetKey,
    onEvent,
    onFormError,
    onFormSuccess,
    onPageReady,
    onReady,
    onThemeChanged,
    shouldDispatchEvents,
} from '../../../public/authkit/js/core/events.js';

import {
    installAuthKitConfig,
    resetCoreTestEnvironment,
} from './support/core-test-helpers.js';


describe('core/events', () => {
    beforeEach(() => {
        resetCoreTestEnvironment();
        installAuthKitConfig();
    });

    it('resolves configured event names and defaults', () => {
        expect(getEventName('ready')).toBe('authkit:ready');
        expect(getEventName('theme_changed')).toBe('authkit:theme:changed');
        expect(getEventName('unknown', 'fallback:event')).toBe('fallback:event');
    });

    it('resolves event target settings', () => {
        expect(shouldDispatchEvents()).toBe(true);
        expect(getEventTargetKey()).toBe('document');
        expect(getEventTarget()).toBe(document);
    });

    it('builds normalized event detail payloads', () => {
        const detail = createEventDetail('ready', { pageKey: 'login' });

        expect(detail.eventKey).toBe('ready');
        expect(detail.pageKey).toBe('login');
        expect(typeof detail.timestamp).toBe('number');
    });

    it('dispatches configured custom events', () => {
        const handler = vi.fn();

        document.addEventListener('authkit:ready', handler);

        const event = dispatchEvent('ready', { pageKey: 'login' });

        expect(event).not.toBeNull();
        expect(handler).toHaveBeenCalledTimes(1);
        expect(handler.mock.calls[0][0].detail.pageKey).toBe('login');
    });

    it('does not dispatch when event dispatching is disabled', () => {
        installAuthKitConfig({
            runtime: {
                windowKey: 'AuthKit',
                dispatchEvents: false,
                eventTarget: 'document',
            },
        });

        const handler = vi.fn();
        document.addEventListener('authkit:ready', handler);

        const event = dispatchEvent('ready');

        expect(event).toBeNull();
        expect(handler).not.toHaveBeenCalled();
    });

    it('attaches logical event listeners and cleanup callbacks', () => {
        const handler = vi.fn();

        const cleanup = onEvent('ready', handler);

        dispatchEvent('ready');
        expect(handler).toHaveBeenCalledTimes(1);

        cleanup();
        dispatchEvent('ready');
        expect(handler).toHaveBeenCalledTimes(1);
    });

    it('exposes convenience listener helpers', () => {
        const onReadyHandler = vi.fn();
        const onThemeChangedHandler = vi.fn();
        const onFormSuccessHandler = vi.fn();
        const onFormErrorHandler = vi.fn();
        const onPageReadyHandler = vi.fn();

        onReady(onReadyHandler);
        onThemeChanged(onThemeChangedHandler);
        onFormSuccess(onFormSuccessHandler);
        onFormError(onFormErrorHandler);
        onPageReady(onPageReadyHandler);

        dispatchEvent('ready');
        dispatchEvent('theme_changed');
        dispatchEvent('form_success');
        dispatchEvent('form_error');
        dispatchEvent('page_ready');

        expect(onReadyHandler).toHaveBeenCalledTimes(1);
        expect(onThemeChangedHandler).toHaveBeenCalledTimes(1);
        expect(onFormSuccessHandler).toHaveBeenCalledTimes(1);
        expect(onFormErrorHandler).toHaveBeenCalledTimes(1);
        expect(onPageReadyHandler).toHaveBeenCalledTimes(1);
    });

    it('returns the default event name map', () => {
        const defaults = getDefaultEventNames();

        expect(defaults.ready).toBe('authkit:ready');
        expect(defaults.page_ready).toBe('authkit:page:ready');
    });
});