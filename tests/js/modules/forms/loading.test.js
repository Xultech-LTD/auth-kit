/**
 * AuthKit
 * -----------------------------------------------------------------------------
 * File: tests/js/modules/forms/loading.test.js
 * Author: Michael Erastus
 * Package: AuthKit
 *
 * Description:
 * -----------------------------------------------------------------------------
 * Unit tests for AuthKit form loading-state utilities.
 *
 * Responsibilities:
 * - Verify loading configuration resolution.
 * - Verify submit control discovery.
 * - Verify loading state application to submit controls.
 * - Verify loading text/type overrides.
 * - Verify loading state restoration after submission.
 */

import { beforeEach, describe, expect, it } from 'vitest';

import {
    applyButtonLoadingState,
    applyLoadingState,
    captureButtonState,
    clearButtonLoadingState,
    clearLoadingState,
    getButtonLabelElement,
    getButtonLoaderElement,
    getLoadingConfig,
    getSubmitButtons,
    resolveButtonLoadingText,
    resolveButtonLoadingType,
} from '../../../../resources/js/authkit/modules/forms/loading.js';


describe('modules/forms/loading', () => {
    beforeEach(() => {
        document.body.innerHTML = '';
    });

    describe('getLoadingConfig', () => {
        it('returns normalized defaults when context is missing', () => {
            const config = getLoadingConfig(null);

            expect(config).toEqual({
                enabled: true,
                preventDoubleSubmit: true,
                disableSubmit: true,
                setAriaBusy: true,
                type: 'spinner_text',
                text: 'Processing...',
                showText: true,
                html: null,
                className: 'authkit-btn--loading',
            });
        });

        it('resolves values from runtime context config', () => {
            const context = {
                config: {
                    forms: {
                        loading: {
                            enabled: false,
                            preventDoubleSubmit: false,
                            disableSubmit: false,
                            setAriaBusy: false,
                            type: 'text',
                            text: 'Submitting...',
                            showText: false,
                            html: '<span>Loader</span>',
                            className: 'custom-loading',
                        },
                    },
                },
            };

            const config = getLoadingConfig(context);

            expect(config).toEqual({
                enabled: false,
                preventDoubleSubmit: false,
                disableSubmit: false,
                setAriaBusy: false,
                type: 'text',
                text: 'Submitting...',
                showText: false,
                html: '<span>Loader</span>',
                className: 'custom-loading',
            });
        });

        it('allows per-call loading options to override context config', () => {
            const context = {
                config: {
                    forms: {
                        loading: {
                            type: 'spinner',
                            text: 'Please wait...',
                            className: 'from-context',
                        },
                    },
                },
            };

            const config = getLoadingConfig(context, {
                loading: {
                    type: 'text',
                    text: 'Saving...',
                    className: 'from-options',
                },
            });

            expect(config.type).toBe('text');
            expect(config.text).toBe('Saving...');
            expect(config.className).toBe('from-options');
        });
    });

    describe('submit control discovery', () => {
        it('resolves submit-capable buttons and inputs only', () => {
            document.body.innerHTML = `
                <form>
                    <button>Default Submit</button>
                    <button type="submit">Explicit Submit</button>
                    <button type="button">Normal Button</button>
                    <input type="submit" value="Send">
                    <input type="image" src="/x.png">
                    <input type="text" name="email">
                </form>
            `;

            const form = document.querySelector('form');
            const buttons = getSubmitButtons(form);

            expect(buttons).toHaveLength(4);
        });

        it('returns empty array for invalid form input', () => {
            expect(getSubmitButtons(null)).toEqual([]);
            expect(getSubmitButtons(document.createElement('div'))).toEqual([]);
        });
    });

    describe('button element helpers', () => {
        it('resolves internal label and loader elements', () => {
            document.body.innerHTML = `
                <button class="authkit-btn">
                    <span class="authkit-btn__content">
                        <span class="authkit-btn__loader" data-authkit-button-loader="1"></span>
                        <span class="authkit-btn__label" data-authkit-button-label="1">Continue</span>
                    </span>
                </button>
            `;

            const button = document.querySelector('button');

            expect(getButtonLoaderElement(button)).toBeInstanceOf(HTMLElement);
            expect(getButtonLabelElement(button)).toBeInstanceOf(HTMLElement);
        });

        it('returns null for invalid button input', () => {
            expect(getButtonLoaderElement(null)).toBeNull();
            expect(getButtonLabelElement(null)).toBeNull();
        });
    });

    describe('loading text and type resolution', () => {
        it('prefers per-button loading attributes over config', () => {
            document.body.innerHTML = `
                <button
                    data-authkit-loading-type="text"
                    data-authkit-loading-text="Logging in..."
                >
                    <span data-authkit-button-loader="1"></span>
                    <span data-authkit-button-label="1">Continue</span>
                </button>
            `;

            const button = document.querySelector('button');
            const config = {
                type: 'spinner_text',
                text: 'Processing...',
            };

            expect(resolveButtonLoadingType(button, config)).toBe('text');
            expect(resolveButtonLoadingText(button, config)).toBe('Logging in...');
        });

        it('falls back to config when button attributes are missing', () => {
            const button = document.createElement('button');

            const config = {
                type: 'spinner',
                text: 'Please wait...',
            };

            expect(resolveButtonLoadingType(button, config)).toBe('spinner');
            expect(resolveButtonLoadingText(button, config)).toBe('Please wait...');
        });
    });

    describe('captureButtonState', () => {
        it('captures original button state safely', () => {
            document.body.innerHTML = `
                <button class="authkit-btn" disabled>
                    <span class="authkit-btn__content">
                        <span class="authkit-btn__loader" data-authkit-button-loader="1" hidden></span>
                        <span class="authkit-btn__label" data-authkit-button-label="1">Continue</span>
                    </span>
                </button>
            `;

            const button = document.querySelector('button');
            const snapshot = captureButtonState(button);

            expect(snapshot).toEqual({
                disabled: true,
                labelHtml: 'Continue',
                loaderHtml: '',
                loaderHidden: true,
                ariaBusy: null,
            });
        });

        it('returns null for invalid button input', () => {
            expect(captureButtonState(null)).toBeNull();
        });
    });

    describe('applyButtonLoadingState and clearButtonLoadingState', () => {
        it('applies spinner_text loading state to a button', () => {
            document.body.innerHTML = `
                <button class="authkit-btn">
                    <span class="authkit-btn__content">
                        <span class="authkit-btn__loader" data-authkit-button-loader="1"></span>
                        <span class="authkit-btn__label" data-authkit-button-label="1">Continue</span>
                    </span>
                </button>
            `;

            const button = document.querySelector('button');
            const label = getButtonLabelElement(button);
            const loader = getButtonLoaderElement(button);

            const applied = applyButtonLoadingState(button, {
                type: 'spinner_text',
                text: 'Processing...',
                disableSubmit: true,
                showText: true,
                className: 'authkit-btn--loading',
            });

            expect(applied).toBe(true);
            expect(button.classList.contains('authkit-btn--loading')).toBe(true);
            expect(button.disabled).toBe(true);
            expect(button.getAttribute('data-authkit-loading')).toBe('true');
            expect(button.getAttribute('aria-busy')).toBe('true');
            expect(label.textContent).toBe('Processing...');
            expect(loader.hidden).toBe(false);
        });

        it('applies text-only loading state and hides loader element', () => {
            document.body.innerHTML = `
                <button class="authkit-btn">
                    <span class="authkit-btn__content">
                        <span class="authkit-btn__loader" data-authkit-button-loader="1"></span>
                        <span class="authkit-btn__label" data-authkit-button-label="1">Continue</span>
                    </span>
                </button>
            `;

            const button = document.querySelector('button');
            const label = getButtonLabelElement(button);
            const loader = getButtonLoaderElement(button);

            applyButtonLoadingState(button, {
                type: 'text',
                text: 'Submitting...',
                disableSubmit: true,
                showText: true,
                className: 'authkit-btn--loading',
            });

            expect(label.textContent).toBe('Submitting...');
            expect(loader.hidden).toBe(true);
        });

        it('applies spinner-only loading state and clears label text', () => {
            document.body.innerHTML = `
                <button class="authkit-btn">
                    <span class="authkit-btn__content">
                        <span class="authkit-btn__loader" data-authkit-button-loader="1"></span>
                        <span class="authkit-btn__label" data-authkit-button-label="1">Continue</span>
                    </span>
                </button>
            `;

            const button = document.querySelector('button');
            const label = getButtonLabelElement(button);

            applyButtonLoadingState(button, {
                type: 'spinner',
                text: 'Processing...',
                disableSubmit: true,
                showText: true,
                className: 'authkit-btn--loading',
            });

            expect(label.textContent).toBe('');
        });

        it('applies custom_html to the loader wrapper', () => {
            document.body.innerHTML = `
                <button class="authkit-btn">
                    <span class="authkit-btn__content">
                        <span class="authkit-btn__loader" data-authkit-button-loader="1"></span>
                        <span class="authkit-btn__label" data-authkit-button-label="1">Continue</span>
                    </span>
                </button>
            `;

            const button = document.querySelector('button');
            const loader = getButtonLoaderElement(button);

            applyButtonLoadingState(button, {
                type: 'custom_html',
                text: 'Processing...',
                html: '<span class="custom-loader">...</span>',
                disableSubmit: true,
                showText: true,
                className: 'authkit-btn--loading',
            });

            expect(loader.innerHTML).toContain('custom-loader');
        });

        it('restores original button state after clearing loading', () => {
            document.body.innerHTML = `
                <button class="authkit-btn">
                    <span class="authkit-btn__content">
                        <span class="authkit-btn__loader" data-authkit-button-loader="1"></span>
                        <span class="authkit-btn__label" data-authkit-button-label="1">Continue</span>
                    </span>
                </button>
            `;

            const button = document.querySelector('button');
            const label = getButtonLabelElement(button);

            applyButtonLoadingState(button, {
                type: 'spinner_text',
                text: 'Processing...',
                disableSubmit: true,
                showText: true,
                className: 'authkit-btn--loading',
            });

            const cleared = clearButtonLoadingState(button, {
                className: 'authkit-btn--loading',
            });

            expect(cleared).toBe(true);
            expect(button.classList.contains('authkit-btn--loading')).toBe(false);
            expect(button.hasAttribute('data-authkit-loading')).toBe(false);
            expect(button.hasAttribute('aria-busy')).toBe(false);
            expect(button.disabled).toBe(false);
            expect(label.innerHTML).toBe('Continue');
        });
    });

    describe('applyLoadingState and clearLoadingState', () => {
        it('applies loading state to all submit controls in a form', () => {
            document.body.innerHTML = `
                <form>
                    <button class="authkit-btn">
                        <span class="authkit-btn__content">
                            <span class="authkit-btn__loader" data-authkit-button-loader="1"></span>
                            <span class="authkit-btn__label" data-authkit-button-label="1">Continue</span>
                        </span>
                    </button>

                    <input type="submit" value="Send">
                </form>
            `;

            const form = document.querySelector('form');

            const count = applyLoadingState(form, {
                config: {
                    forms: {
                        loading: {
                            enabled: true,
                            disableSubmit: true,
                            setAriaBusy: true,
                            type: 'spinner_text',
                            text: 'Processing...',
                            showText: true,
                            className: 'authkit-btn--loading',
                        },
                    },
                },
            });

            expect(count).toBe(2);
            expect(form.getAttribute('aria-busy')).toBe('true');
            expect(form.getAttribute('data-authkit-submitting')).toBe('true');
        });

        it('does nothing when loading is disabled', () => {
            document.body.innerHTML = `
                <form>
                    <button class="authkit-btn">
                        <span data-authkit-button-loader="1"></span>
                        <span data-authkit-button-label="1">Continue</span>
                    </button>
                </form>
            `;

            const form = document.querySelector('form');

            const count = applyLoadingState(form, {
                config: {
                    forms: {
                        loading: {
                            enabled: false,
                        },
                    },
                },
            });

            expect(count).toBe(0);
            expect(form.hasAttribute('aria-busy')).toBe(false);
            expect(form.hasAttribute('data-authkit-submitting')).toBe(false);
        });

        it('clears loading state from the form and its buttons', () => {
            document.body.innerHTML = `
                <form>
                    <button class="authkit-btn">
                        <span class="authkit-btn__content">
                            <span class="authkit-btn__loader" data-authkit-button-loader="1"></span>
                            <span class="authkit-btn__label" data-authkit-button-label="1">Continue</span>
                        </span>
                    </button>
                </form>
            `;

            const form = document.querySelector('form');

            applyLoadingState(form, {
                config: {
                    forms: {
                        loading: {
                            enabled: true,
                            disableSubmit: true,
                            setAriaBusy: true,
                            type: 'spinner_text',
                            text: 'Processing...',
                            showText: true,
                            className: 'authkit-btn--loading',
                        },
                    },
                },
            });

            const count = clearLoadingState(form, {
                config: {
                    forms: {
                        loading: {
                            className: 'authkit-btn--loading',
                        },
                    },
                },
            });

            expect(count).toBe(1);
            expect(form.hasAttribute('aria-busy')).toBe(false);
            expect(form.hasAttribute('data-authkit-submitting')).toBe(false);
        });
    });
});