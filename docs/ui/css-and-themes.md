# CSS and Themes

## Overview

AuthKit’s styling system is configuration-driven.

Instead of hardcoding a single visual presentation, AuthKit separates its UI styling into a few clear layers:

- **assets** define where AuthKit CSS and JavaScript files are loaded from
- **ui** controls the active engine, theme, appearance mode, and runtime behavior
- **themes** define the available theme names and how stylesheet filenames are resolved
- **components** remain semantic, so styling can change without rewriting page markup

This means you can change how AuthKit looks by updating configuration rather than rewriting package internals.

At a high level, AuthKit is designed so that:

1. Blade templates render semantic AuthKit components and classes
2. the configured UI engine and theme decide which stylesheet is loaded
3. the configured appearance mode decides whether light, dark, or system mode is used
4. optional JavaScript handles runtime concerns such as mode persistence and theme toggling

## Styling Philosophy

AuthKit does not assume that one design system fits every application.

The package is built around a few principles:

### Semantic markup first

AuthKit components render semantic package-level classes such as:

- `authkit-card`
- `authkit-input`
- `authkit-btn`
- `authkit-field`

This keeps page and component structure stable even when the visual system changes.

### Engine and theme are separate concerns

AuthKit treats the following as distinct:

- **engine**: the styling family or design language
- **theme**: the palette and brand skin inside that engine
- **mode**: the current appearance state such as light or dark

That separation allows you to keep the same components while changing visual tone.

### Configuration over template rewrites

Most styling decisions are made from config. This gives consumers a simple way to:

- switch themes
- switch engines
- disable packaged assets
- load extra override files
- replace the theme toggle behavior
- integrate AuthKit into an existing application design system


## Asset Base Path

AuthKit publishes its frontend assets into a public directory controlled by `authkit.assets.base_path`.

```php
'assets' => [
    'base_path' => 'vendor/authkit',
],
```

With this configuration, AuthKit expects published assets under paths such as:

```html
public/vendor/authkit/css/themes/tailwind-slate-gold.css
public/vendor/authkit/js/authkit.js
```

This base path is important because multiple UI features depend on it:
- packaged theme stylesheet loading
- packaged browser runtime loading
- extra CSS and JavaScript extension files
- image or static files you may want to colocate with AuthKit assets

##cBase Assets

AuthKit also allows optional base CSS and JavaScript assets through:
```php
'assets' => [
    'base' => [
        'css' => [
            // 'css/authkit.css',
        ],
        'js' => [
            // 'js/authkit.js',
        ],
    ],
],
```
These entries are relative to `public/{assets.base_path}`.

Typical use cases include:

- loading a shared base stylesheet before the active theme file
- loading foundational package runtime files
- adding pre-theme utility styles that all themes depend on
- splitting common package styling from theme-specific styling

If you do not need separate base assets, these arrays can remain empty.

## UI Configuration

The main styling behavior lives under `authkit.ui`.

### Default engine

```php
'ui' => [
    'engine' => 'tailwind',
],
```
The engine controls the overall styling family used by AuthKit.

In the current configuration, supported engines are:

- `tailwind`
- `bootstrap`

These names refer to AuthKit’s packaged design families. They do not mean the consuming application must compile Tailwind or include Bootstrap itself.

A Tailwind-based AuthKit theme still works as packaged CSS, and a Bootstrap-based AuthKit theme still works as packaged CSS.

## Default theme

```php
'ui' => [
    'theme' => 'slate-gold',
],
```
The theme controls the color and visual personality within the selected engine.

For example:

- `tailwind + slate-gold`
- `bootstrap + slate-gold`

may share the same broad brand palette but still differ in component language because the engine is different.

## Default mode

```php
'ui' => [
    'mode' => 'light',
],
```
Supported values are:

- `light`
- `dark`
- `system`

These control the initial appearance behavior.

### `light`

Always render AuthKit in light mode.

### `dark`

Always render AuthKit in dark mode.

### `system`

Let the browser runtime resolve the mode using the user’s operating-system preference.

## Engine and Theme Resolution

AuthKit resolves its stylesheet using the active engine, active theme, and the theme filename pattern.

Your configuration includes:

```php
'themes' => [
    'file_pattern' => '{engine}-{theme}.css',
],
```
So with:

```php
'ui' => [
    'engine' => 'tailwind',
    'theme' => 'slate-gold',
],
```

the resolved stylesheet filename becomes:
```php
tailwind-slate-gold.css
```

And AuthKit expects that file under:
```php
public/vendor/authkit/css/themes/tailwind-slate-gold.css
```

If you switch to:
```php
'ui' => [
    'engine' => 'bootstrap',
    'theme' => 'forest',
],
```

the expected stylesheet becomes:
```php
public/vendor/authkit/css/themes/bootstrap-forest.css
```

This pattern-based approach keeps theme resolution predictable and easy to extend.

## Available Engines and Themes

The `authkit.themes` section defines which engine names and theme names AuthKit recognizes.
```php
'themes' => [
    'engines' => [
        'tailwind',
        'bootstrap',
    ],
    'available' => [
        'tailwind' => [
            'amber-silk',
            'aurora',
            'forest',
            'imperial-gold',
            'ivory-gold',
            'midnight-blue',
            'neutral',
            'noir-grid',
            'ocean-mist',
            'paper-ink',
            'red-beige',
            'rose-ash',
            'slate-gold',
        ],
        'bootstrap' => [
            'amber-silk',
            'aurora',
            'forest',
            'imperial-gold',
            'ivory-gold',
            'midnight-blue',
            'neutral',
            'noir-grid',
            'ocean-mist',
            'paper-ink',
            'red-beige',
            'rose-ash',
            'slate-gold',
        ],
    ],
],
```

This section is primarily descriptive and organizational, but it is still useful because it provides:

- a documented list of packaged theme names
- a stable source for future validation or tooling
- a clear place for consumers to register custom theme names after adding their own files

If you create a custom theme file, you should also add its name to the relevant engine list so configuration remains self-documenting.

## Packaged Theme Stylesheet Loading

AuthKit can automatically include the active theme stylesheet.
```php
'ui' => [
    'load_stylesheet' => true,
],
```

When this is enabled, AuthKit’s layout resolves the configured engine and theme and loads the matching theme file from the published assets directory.

This is the default and recommended mode for most applications.

### When to keep this enabled

Keep automatic stylesheet loading enabled when:

- you want to use AuthKit’s packaged themes directly
- you want simple theme switching from config
- you want the package layout to manage stylesheet resolution for you

### When to disable it

You may disable it if your application wants to:

- bundle AuthKit theme CSS into its own build pipeline
- load a completely custom stylesheet instead
- fully replace AuthKit’s packaged presentation layer

**Example:**
```php
'ui' => [
    'load_stylesheet' => false,
],
```
When disabled, you become responsible for loading all required AuthKit styles yourself.

## Packaged JavaScript Loading

AuthKit can also automatically load its packaged frontend runtime.
```php
'ui' => [
    'load_script' => true,
],
```

This is separate from CSS loading but closely related to themes because the runtime may handle:

- light, dark, or system mode resolution
- storing user mode preference
- syncing the theme toggle component
- AJAX form submission behavior
- page-specific UI enhancements

If you disable script loading, AuthKit pages can still render and submit normally, but runtime behaviors become your responsibility.

## Data Attributes

AuthKit can emit stable data attributes on the layout root.
```php

'ui' => [
    'use_data_attributes' => true,
],
```

When enabled, AuthKit may render attributes such as:
```blade
<div
    data-authkit-engine="tailwind"
    data-authkit-theme="slate-gold"
    data-authkit-mode="light"
>
```

These attributes are valuable because they provide stable hooks for:

- package CSS targeting
- consumer CSS overrides
- JavaScript runtime behavior
- external integrations

This allows styling and scripting to depend on explicit UI state rather than brittle selectors.

For example, a consumer stylesheet might target:

```css
[data-authkit-theme="slate-gold"] .authkit-card {
    /* custom overrides */
}
```
Or a custom script might read the current mode and update another part of the host application.

## Appearance Mode Persistence

AuthKit can remember the user’s chosen appearance mode across visits.
```php
'ui' => [
    'persistence' => [
        'enabled' => true,
        'storage_key' => 'authkit.ui.mode',
    ],
],
```

`enabled`

When enabled, AuthKit’s runtime may persist the selected appearance mode in browser storage.

`storage_key`

This key is used to store the selected mode.

With the current configuration, the saved value is stored under:

`authkit.ui.mode`

This key should remain stable once your application is live. Changing it later may cause previously saved preferences to stop being recognized.

### Typical behavior

If persistence is enabled and the user changes the appearance mode through a toggle:

- the new mode is stored in browser storage 
- subsequent page loads restore that preference
- the resolved mode is reflected in the layout and theme runtime

## Theme Toggle

AuthKit includes an optional theme toggle system.
```php
'ui' => [
    'toggle' => [
        'enabled' => true,
        'variant' => 'icon',
        'allow_system' => false,
        'show_labels' => true,
        'attribute' => 'data-authkit-theme-toggle',
    ],
],
```
`enabled`

Controls whether the packaged theme toggle component is available for use.

This does not force the toggle to appear automatically on every page. It simply enables the packaged mechanism.

`variant`

Defines the default presentation used by the packaged toggle component.

Suggested values include:

- `auto`
- `dropdown`
- `buttons` 
- `icon`

The current configuration uses:

```php
'variant' => 'icon',
```

which means the default packaged toggle should render in an icon-oriented style.

`allow_system`

Controls whether the toggle should expose system as a user-selectable option.

The current config is:

```php
'allow_system' => false,
```
so the packaged toggle should offer only light and dark choices.

If you want users to choose light, dark, or system, set it to:

```php
'allow_system' => true,
```

`show_labels`

Controls whether labels should appear beside icons where relevant.

`attribute`

This attribute identifies theme toggle controls for AuthKit’s JavaScript runtime.

Current value:

```php
'attribute' => 'data-authkit-theme-toggle',
```

**Example usage:**

```blade 
<button data-authkit-theme-toggle="dark">Dark</button>
<button data-authkit-theme-toggle="light">Light</button>
```

AuthKit’s runtime can discover these controls and bind mode-switching behavior automatically.

## CSS Extension Hooks

AuthKit supports extra CSS files that load after the packaged theme.
```php
'ui' => [
    'extensions' => [
        'extra_css' => [
            // 'css/authkit-overrides.css',
        ],
    ],
],
```

These files are relative to `public/{assets.base_path}` unless your layout resolves them differently.

### Why this matters

This is the easiest way to apply design overrides without replacing the full theme.

**Typical use cases:**

- branding adjustments
- button radius changes
- spacing refinements
- form layout overrides
- app shell customizations
- per-project tweaks on top of a packaged theme

**Example**

```php
'ui' => [
    'extensions' => [
        'extra_css' => [
            'css/authkit-overrides.css',
        ],
    ],
],
```

With `assets.base_path = vendor/authkit`, AuthKit will look for:

```text
public/vendor/authkit/css/authkit-overrides.css
```

Because this file loads after the packaged stylesheet, it is ideal for non-invasive overrides.

## JavaScript Extension Hooks

The same idea applies to additional scripts.

```php
'ui' => [
    'extensions' => [
        'extra_js' => [
            // 'js/authkit-overrides.js',
        ],
    ],
],
```

These files can be used to extend theme-related runtime behavior, including:
- custom toggles
- analytics hooks
- additional mode synchronization logic
- extra UI polish on AuthKit pages

## Root Hooks for Consumer Overrides

AuthKit can expose stable root hooks for consumer CSS.

```php
'ui' => [
    'extensions' => [
        'enable_root_hooks' => true,
    ],
],
```

When enabled, consumers can rely on selectors such as:

- `.authkit`
- `[data-authkit-engine]`
- `[data-authkit-theme]`
- `[data-authkit-mode]`

This helps you write project-specific CSS without needing to replace package views or theme files.

This option should usually remain enabled.

## Components and CSS

The `authkit.components` configuration and the CSS/theme system are closely related.

AuthKit components are semantic renderers, while themes are the visual layer applied on top of them.

For example:
```php
'components' => [
    'card' => 'authkit::card',
    'input' => 'authkit::form.input',
    'button' => 'authkit::button',
],
```

These components should render consistent semantic markup and AuthKit classes. The theme file then styles those classes according to the active engine and theme.

This separation matters because it means you can:

- keep the same components and change the theme
- keep the same theme and replace the components
- replace both when necessary

### Recommended approach

**For most applications:**

- keep component structure compatible with AuthKit
- use `themes + extra_css` for styling
- replace components only when structure must change

### Authenticated App Styling

All authenticated pages inherit the same theme system:

- dashboard 
- settings 
- security 
- sessions 
- two-factor settings 
- confirmation pages

Core components:

```php
'app_layout' => 'authkit::app.layout',
'app_shell' => 'authkit::app.shell',
'app_sidebar' => 'authkit::app.sidebar',
'app_topbar' => 'authkit::app.topbar',
```

> Design them to be theme-aware, not hardcoded.

## Theme Files and Custom Themes

If you want to add your own theme, the normal approach is:

- create the CSS file in the published themes directory 
- name it according to the configured file pattern 
- add the theme name under the appropriate engine in authkit.themes.available 
- update `authkit.ui.theme`

**Example**

Suppose you want a custom Tailwind-style theme called sunset.

You would add:

```php
'themes' => [
    'available' => [
        'tailwind' => [
            'slate-gold',
            'sunset',
        ],
    ],
],
```

Then create:

```text
public/vendor/authkit/css/themes/tailwind-sunset.css
```

Then set:
```php
'ui' => [
    'engine' => 'tailwind',
    'theme' => 'sunset',
],
```
AuthKit will now resolve and load tailwind-sunset.css.

### Using a Completely Custom Stylesheet

Some consumers may want to fully control styling.

A common setup is:
```php
'ui' => [
    'load_stylesheet' => false,
],
```

and then load your own compiled CSS from the application layout.

This approach is useful when:

- your app already has a strong design system 
- AuthKit should visually blend into an existing frontend 
- you want to avoid loading multiple style sources 
- you prefer to treat AuthKit as a semantic Blade/rendering layer only

In that case, you should still preserve semantic AuthKit classes or data hooks where needed, so your custom stylesheet has reliable targets.
