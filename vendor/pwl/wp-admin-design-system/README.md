# WP Admin Design System

A design system for WordPress admin plugins — design tokens, typography, components, and a PHP helper API. Built to be installed via Composer and reused across multiple plugins without writing a single line of repeated CSS or markup.

**Accent color:** Amber Bronze — warm, serious, trustworthy. No blue. No red.
**Fonts:** [Lora](https://fonts.google.com/specimen/Lora) (headings) + [Plus Jakarta Sans](https://fonts.google.com/specimen/Plus+Jakarta+Sans) (body)
**Scope:** All styles live under the `.wads` class — zero conflicts with WordPress admin CSS.

---

## Table of Contents

1. [Installation](#installation)
2. [Quick Start](#quick-start)
3. [How It Works](#how-it-works)
4. [App Shell & Sidebar](#app-shell--sidebar)
5. [PHP Components API](#php-components-api)
   - [Button](#button)
   - [Notice](#notice)
   - [Badge](#badge)
   - [Chip](#chip)
   - [Card](#card)
   - [Form Inputs](#form-inputs)
   - [Form Group](#form-group)
   - [Settings Section](#settings-section)
   - [Setting Row](#setting-row)
   - [Page Header](#page-header)
   - [Stat Card](#stat-card)
   - [Progress Bar](#progress-bar)
   - [Spinner](#spinner)
   - [Empty State](#empty-state)
   - [Breadcrumbs](#breadcrumbs)
   - [Callout](#callout)
   - [Key-Value List](#key-value-list)
6. [CSS-Only Components](#css-only-components)
   - [Tabs](#tabs)
   - [Table](#table)
   - [Accordion](#accordion)
   - [Dropdown](#dropdown)
   - [Modal](#modal)
   - [Timeline](#timeline)
   - [Steps / Wizard](#steps--wizard)
   - [Pagination](#pagination)
   - [Search Bar](#search-bar)
   - [Copy Block](#copy-block)
7. [Design Tokens Reference](#design-tokens-reference)
8. [Layout Utilities](#layout-utilities)
9. [Best Practices](#best-practices)
10. [Customization](#customization)
11. [Demo Files](#demo-files)

---

## Installation

```bash
composer require userdomp/wp-admin-design-system
```

Requires PHP 8.0+ and WordPress 6.0+.

---

## Quick Start

### 1. Enqueue assets in your plugin

In your plugin's main PHP file (or wherever you register admin scripts):

```php
use UserDOMP\WpAdminDS\DesignSystem;

add_action('admin_enqueue_scripts', function() {
    DesignSystem::enqueue(
        DesignSystem::assets_url(__FILE__)
    );
});
```

`assets_url(__FILE__)` resolves the correct URL to the package's `/assets/` directory relative to your plugin's root, assuming the standard Composer `vendor/` path.

### 2. Wrap your page in `.wads`

```php
echo '<div class="wads">';
// Your admin page content
echo '</div>';
```

That's it. All design system styles are scoped to `.wads` so they never bleed into WordPress admin styles.

### 3. Use the PHP helpers

```php
use UserDOMP\WpAdminDS\Components;

echo Components::notice('Settings saved.', 'success');

echo Components::settings_section([
    'title'  => 'API Connection',
    'desc'   => 'Configure your Bsale credentials.',
    'rows'   => [
        [
            'label'   => 'Access Token',
            'desc'    => 'Found in Bsale → Settings → API.',
            'control' => Components::input('my_plugin_api_token', ['type' => 'password']),
        ],
        [
            'label'   => 'Sandbox Mode',
            'control' => Components::toggle('my_plugin_sandbox', 'Enable sandbox'),
        ],
    ],
    'footer' => Components::button('Save Changes'),
]);
```

---

## How It Works

### The `.wads` scope

Every CSS rule in this system uses `.wads` as a parent selector:

```css
.wads h1 { ... }
.wads .wads-btn { ... }
.wads .wads-card { ... }
```

This means:
- **No conflicts** with WordPress admin styles (`wp-admin.css`, WooCommerce admin, etc.)
- **Safe to use** on any admin page — global styles are untouched
- **Easy to remove** — delete the wrapper and everything reverts

Wrap any page, metabox, widget, or tab content in `<div class="wads">` to activate it.

### CSS classes follow BEM-lite naming

- Block: `.wads-card`
- Element: `.wads-card__header`, `.wads-card__body`
- Modifier: `.wads-card--flat`, `.wads-btn--primary`

### PHP helpers are thin wrappers

The `Components` class generates HTML strings. Every user-supplied string is escaped with `esc_html()` / `esc_attr()` / `esc_url()`. HTML passed in `body`, `footer`, and similar slots is **not escaped** — you are responsible for escaping your own HTML content.

---

## App Shell & Sidebar

Use the app shell layout for complex plugins that need their own sub-navigation within the WordPress admin.

```
WordPress Admin Chrome (top bar + WP sidebar)
└── #wpcontent
    └── <div class="wads wads-app-shell">
            <aside class="wads-sidebar">...</aside>
            <main class="wads-main">...</main>
        </div>
```

### Sidebar HTML structure

```html
<div class="wads wads-app-shell">

    <aside class="wads-sidebar">

        <!-- Plugin name & version -->
        <div class="wads-sidebar__brand">
            <span class="wads-sidebar__plugin-name">My Plugin</span>
            <span class="wads-sidebar__plugin-version">v1.0.0 · Pro</span>
        </div>

        <!-- Navigation -->
        <nav class="wads-sidebar__nav">

            <div class="wads-nav-group">
                <span class="wads-nav-label">Documents</span>

                <a class="wads-nav-item is-active" href="?page=my-plugin">
                    DTE List
                </a>

                <a class="wads-nav-item" href="?page=my-plugin-pending">
                    Pending Retry
                    <span class="wads-nav-item__end">
                        <span class="wads-badge wads-badge--solid-danger" style="font-size:9px">4</span>
                    </span>
                </a>
            </div>

            <div class="wads-nav-group">
                <span class="wads-nav-label">Settings</span>

                <a class="wads-nav-item" href="?page=my-plugin-settings">API & Connection</a>

                <!-- Pro-only item -->
                <a class="wads-nav-item" href="?page=my-plugin-webhooks">
                    Webhooks
                    <span class="wads-nav-item__end">
                        <span class="wads-badge wads-badge--solid-accent">Pro</span>
                    </span>
                </a>
            </div>

        </nav>

        <!-- Footer links -->
        <div class="wads-sidebar__footer">
            <a class="wads-sidebar__footer-link" href="https://docs.example.com">Documentation</a>
            <a class="wads-sidebar__footer-link" href="https://example.com/support">Support</a>
        </div>

    </aside>

    <!-- Main content -->
    <main class="wads-main">
        <!-- Optional topbar inside main -->
        <div class="wads-topbar">
            <h2 class="wads-topbar__title">DTE List</h2>
            <button class="wads-btn wads-btn--secondary wads-btn--sm">Sync Stock</button>
        </div>

        <!-- Your page content here -->
    </main>

</div>
```

### Sidebar variants

```html
<!-- Default: dark sidebar (recommended for primary navigation) -->
<aside class="wads-sidebar">...</aside>

<!-- Light sidebar (for secondary or contextual navigation) -->
<aside class="wads-sidebar wads-sidebar--light">...</aside>
```

### When to use the App Shell

| Situation | Use App Shell? |
|---|:---:|
| Plugin has 4+ admin pages | ✅ Yes |
| Plugin has a single settings page | ❌ No — use plain `.wads` |
| Plugin is a WooCommerce extension with tabs | ❌ No — use `.wads-tabs` |
| Plugin has a dashboard with stats + multiple sections | ✅ Yes |

---

## PHP Components API

Import the class at the top of any PHP file that renders admin HTML:

```php
use UserDOMP\WpAdminDS\Components;
```

All methods are `static` and return HTML strings. Use `echo` to render them.

---

### Button

```php
Components::button(string $label, string $variant = 'primary', array $options = []): string
```

**Variants:** `primary` `secondary` `ghost` `danger`

**Options:**

| Key | Type | Default | Description |
|---|---|---|---|
| `size` | string | — | `sm` or `lg` |
| `type` | string | `button` | `button`, `submit`, `reset` |
| `disabled` | bool | `false` | |
| `full` | bool | `false` | Full-width button |
| `href` | string | — | Renders as `<a>` instead of `<button>` |
| `attrs` | array | `[]` | Extra HTML attributes |

**Examples:**

```php
// Standard primary button
echo Components::button('Save Changes');

// Secondary, small
echo Components::button('Cancel', 'secondary', ['size' => 'sm']);

// Submit button
echo Components::button('Save', 'primary', ['type' => 'submit']);

// Disabled danger button
echo Components::button('Delete', 'danger', ['disabled' => true]);

// Link rendered as button
echo Components::button('View Docs', 'ghost', ['href' => 'https://docs.example.com']);

// With custom attributes (e.g. for JS hooks)
echo Components::button('Retry', 'secondary', [
    'attrs' => ['id' => 'btn-retry', 'data-order-id' => '1055'],
]);

// Button group (wrap in .wads-btn-group)
echo '<div class="wads-btn-group">'
    . Components::button('Cancel', 'ghost')
    . Components::button('Save Changes')
    . '</div>';
```

---

### Notice

```php
Components::notice(string $message, string $type = 'info', array $options = []): string
```

**Types:** `success` `warning` `danger` `error` `info` `neutral`

**Options:**

| Key | Type | Description |
|---|---|---|
| `title` | string | Bold title above the message |
| `dismissible` | bool | Adds a dismiss button (requires JS) |
| `banner` | bool | Left-border-only variant (no radius) |

**Examples:**

```php
// Simple success notice
echo Components::notice('Settings saved successfully.', 'success');

// With title and dismissible
echo Components::notice(
    'Check your token and sandbox setting.',
    'danger',
    [
        'title'       => 'API connection failed',
        'dismissible' => true,
    ]
);

// Banner-style (good for page-level notices)
echo Components::notice(
    'This feature is only available in the <strong>Pro</strong> edition.',
    'neutral',
    ['banner' => true]
);

// Warning with HTML in message
echo Components::notice(
    'Only 3 of 52 products failed. <a href="#">View errors</a>',
    'warning',
    ['title' => 'Stock sync incomplete']
);
```

> **Note:** The `$message` parameter is rendered as raw HTML. If it contains user input, escape it with `esc_html()` before passing it in.

---

### Badge

```php
Components::badge(string $label, string $variant = 'default'): string
```

**Variants:**

| Variant | Use case |
|---|---|
| `default` | Neutral label, muted |
| `accent` | Highlighted, branded |
| `success` | Active, issued, complete |
| `warning` | Pending, queued |
| `danger` | Failed, error, void |
| `info` | Informational |
| `solid-accent` | Edition badge (Pro, Lite) |
| `solid-success` | Confirmed status |
| `solid-danger` | Voided, cancelled |
| `dot-success` | With colored dot prefix |
| `dot-warning` | With colored dot prefix |
| `dot-danger` | With colored dot prefix |

**Examples:**

```php
echo Components::badge('Active', 'dot-success');
echo Components::badge('Pending', 'dot-warning');
echo Components::badge('Failed', 'dot-danger');
echo Components::badge('Pro', 'solid-accent');
echo Components::badge('Issued', 'solid-success');
```

---

### Chip

```php
Components::chip(string $label, string $variant = '', array $options = []): string
```

Chips are used for active filters, tags, and multi-select values.

**Variants:** `accent` `success` `danger` _(or empty for default)_

**Options:**

| Key | Type | Description |
|---|---|---|
| `removable` | bool | Adds a remove button |
| `remove_value` | string | Value passed on `data-value` of the remove button |

**Examples:**

```php
// Static chip (filter label)
echo Components::chip('Boleta');
echo Components::chip('Issued', 'success');

// Removable chip (active filter)
echo Components::chip('Failed', 'danger', [
    'removable'     => true,
    'remove_value'  => 'failed',
]);

// Chip list
echo '<div class="wads-chips">'
    . Components::chip('All', 'accent')
    . Components::chip('Boleta')
    . Components::chip('Factura')
    . Components::chip('Failed', 'danger', ['removable' => true])
    . '</div>';
```

---

### Card

```php
Components::card(array $options): string
```

**Options:**

| Key | Type | Description |
|---|---|---|
| `title` | string | Card header title |
| `subtitle` | string | Muted subtitle below the title |
| `header_end` | string | HTML placed at the right side of the header |
| `body` | string | Card body HTML |
| `footer` | string | Card footer HTML (right-aligned) |
| `variant` | string | `flat`, `accent`, `clickable` |

**Examples:**

```php
// Simple card
echo Components::card([
    'title' => 'API Status',
    'body'  => '<p>Connected to Bsale sandbox.</p>',
]);

// Card with subtitle, badge in header, and footer button
echo Components::card([
    'title'      => 'Document Engine',
    'subtitle'   => 'Generates boletas and facturas on order completion',
    'header_end' => Components::badge('Active', 'dot-success'),
    'body'       => $body_html,
    'footer'     => Components::button('Configure', 'secondary', ['size' => 'sm']),
]);

// Flat card (no shadow — good inside other cards or sections)
echo Components::card([
    'title'   => 'Warning',
    'body'    => $html,
    'variant' => 'flat',
]);

// Accent card (left border highlight)
echo Components::card([
    'title'   => 'Pro Feature',
    'body'    => $html,
    'variant' => 'accent',
]);
```

---

### Form Inputs

#### Input

```php
Components::input(string $name, array $options = []): string
```

| Option | Type | Description |
|---|---|---|
| `id` | string | Defaults to `$name` |
| `type` | string | `text`, `password`, `email`, `url`, `number`, `tel` |
| `value` | string | Current value |
| `placeholder` | string | |
| `size` | string | `sm` or `lg` |
| `disabled` | bool | |
| `error` | bool | Applies error border style |
| `attrs` | array | Extra HTML attributes |

```php
echo Components::input('api_token', ['type' => 'password', 'placeholder' => 'Paste your token']);
echo Components::input('office_id', ['type' => 'number', 'value' => '1']);
echo Components::input('webhook_url', ['type' => 'url', 'error' => true]);
```

#### Select

```php
Components::select(string $name, array $choices, array $options = []): string
```

```php
// Flat options
echo Components::select('office_id', [
    '1' => 'Casa Matriz',
    '2' => 'Sucursal Norte',
    '3' => 'Sucursal Sur',
], ['selected' => get_option('my_plugin_office_id')]);

// With placeholder
echo Components::select('document_type', [
    '39' => 'Boleta (39)',
    '33' => 'Factura (33)',
], ['placeholder' => '— Select type —']);

// Grouped options
echo Components::select('shipping_office', [
    'Santiago' => [
        '1' => 'Casa Matriz',
        '2' => 'Sucursal Providencia',
    ],
    'Regiones' => [
        '3' => 'Sucursal Norte',
        '4' => 'Sucursal Sur',
    ],
]);
```

#### Textarea

```php
Components::textarea(string $name, string $value = '', array $options = []): string
```

```php
echo Components::textarea('notes', get_option('my_plugin_notes'), [
    'placeholder' => 'Internal notes about this configuration…',
    'rows'        => 4,
]);
```

#### Checkbox

```php
Components::checkbox(string $name, string $label, array $options = []): string
```

```php
echo Components::checkbox('enable_boleta', 'Issue boleta on order completion', [
    'checked' => (bool) get_option('my_plugin_enable_boleta', true),
    'hint'    => 'Automatically triggered when an order is marked complete.',
]);
```

#### Toggle

```php
Components::toggle(string $name, string $label, array $options = []): string
```

```php
echo Components::toggle('sandbox_mode', 'Enable sandbox mode', [
    'checked' => (bool) get_option('my_plugin_sandbox'),
]);
```

**When to use Toggle vs Checkbox:**
- **Toggle:** On/off settings with an immediate, binary effect (sandbox mode, enable feature).
- **Checkbox:** Multi-select lists, options within a group, or settings that need a submit to apply.

---

### Form Group

Wraps any control with a label, hint, and error message.

```php
Components::form_group(string $label, string $control_html, array $options = []): string
```

| Option | Type | Description |
|---|---|---|
| `for` | string | Label `for` attribute |
| `required` | bool | Appends a red asterisk |
| `hint` | string | Gray help text below the control |
| `error` | string | Red error message |

```php
echo Components::form_group(
    'Access Token',
    Components::input('my_plugin_api_token', ['type' => 'password', 'id' => 'my_plugin_api_token']),
    [
        'for'      => 'my_plugin_api_token',
        'required' => true,
        'hint'     => 'Found in Bsale → Settings → API access.',
    ]
);

// With validation error
echo Components::form_group(
    'RUT',
    Components::input('billing_rut', ['value' => $rut, 'error' => true, 'id' => 'billing_rut']),
    [
        'for'   => 'billing_rut',
        'error' => 'Invalid RUT format. Use XX.XXX.XXX-X.',
    ]
);
```

---

### Settings Section

The primary building block for plugin settings pages.

```php
Components::settings_section(array $options): string
```

| Option | Type | Description |
|---|---|---|
| `title` | string | Section heading |
| `desc` | string | Subtitle below the heading |
| `rows` | array | Array of setting rows (see below) |
| `body` | string | Alternative: raw HTML instead of rows |
| `footer` | string | HTML in the section footer (usually a save button) |

Each row in `rows` accepts:
- `label` — Row label (left column)
- `desc` — Small description below the label
- `control` — HTML of the form control (right column)
- `required` — bool

```php
echo Components::settings_section([
    'title' => 'API Connection',
    'desc'  => 'Credentials to connect with the Bsale API.',
    'rows'  => [
        [
            'label'    => 'Access Token',
            'desc'     => 'Your Bsale API token. Keep it secret.',
            'required' => true,
            'control'  => Components::input('bsale_dte_api_token', [
                'type'  => 'password',
                'value' => get_option('bsale_dte_api_token', ''),
            ]),
        ],
        [
            'label'   => 'Environment',
            'desc'    => 'Use the sandbox API for testing.',
            'control' => Components::toggle('bsale_dte_sandbox', 'Enable sandbox mode', [
                'checked' => (bool) get_option('bsale_dte_sandbox'),
            ]),
        ],
        [
            'label'   => 'Default Office',
            'control' => Components::select('bsale_dte_office_id', $offices, [
                'selected' => get_option('bsale_dte_office_id'),
            ]),
        ],
    ],
    'footer' => Components::button('Save Changes', 'primary', ['type' => 'submit']),
]);
```

---

### Setting Row

Render a single row outside of a section (for custom layouts).

```php
Components::setting_row(string $label, string $control_html, array $options = []): string
```

```php
echo '<div class="wads-settings-section__body">'
    . Components::setting_row('API Token', Components::input('api_token'))
    . Components::setting_row('Sandbox', Components::toggle('sandbox', 'Enable'), ['desc' => 'Use dev API'])
    . '</div>';
```

---

### Page Header

```php
Components::page_header(string $title, array $options = []): string
```

| Option | Type | Description |
|---|---|---|
| `desc` | string | Subtitle below the title |
| `badge` | string | Badge label (rendered as `solid-accent`) |
| `actions` | string | HTML for the right column (buttons, etc.) |

```php
echo Components::page_header('DTE Settings', [
    'desc'    => 'Configure your WooCommerce integration with Bsale.',
    'badge'   => 'Pro',
    'actions' => Components::button('Test Connection', 'secondary', ['size' => 'sm'])
                . Components::button('Save', 'primary', ['type' => 'submit']),
]);
```

---

### Stat Card

```php
Components::stat(string $label, string $value, array $options = []): string
```

| Option | Type | Description |
|---|---|---|
| `change` | string | Change description |
| `trend` | string | `up` (green) or `down` (red) |

```php
// Wrap stats in a grid
echo '<div class="wads-grid wads-grid--4">';
echo Components::stat('Documents Issued', '1,284', ['change' => '+12% this month', 'trend' => 'up']);
echo Components::stat('Pending Retry', '4', ['change' => 'Next attempt in 15 min']);
echo Components::stat('Success Rate', '99.3%', ['change' => 'All time', 'trend' => 'up']);
echo Components::stat('Credit Notes', '18', ['change' => 'This month']);
echo '</div>';
```

---

### Progress Bar

```php
Components::progress(int $value, int $max = 100, array $options = []): string
```

| Option | Type | Description |
|---|---|---|
| `variant` | string | `success` or `danger` |
| `label` | string | Text displayed above the bar with a value/max counter |

```php
echo Components::progress(482, 520, ['label' => 'Stock sync progress']);
echo Components::progress(99, 100, ['variant' => 'success', 'label' => 'DTE success rate']);
echo Components::progress(3, 50, ['variant' => 'danger', 'label' => 'Failed documents']);
```

---

### Spinner

```php
Components::spinner(string $size = ''): string
```

**Sizes:** `sm`, _(default)_, `lg`

```php
// Inline loading indicator
echo '<div style="display:flex;align-items:center;gap:8px">'
    . Components::spinner('sm')
    . '<span class="wads-text-muted">Connecting to Bsale…</span>'
    . '</div>';

// Full-section loading state
echo '<div class="wads-card"><div class="wads-card__body" style="text-align:center;padding:48px">'
    . Components::spinner('lg')
    . '</div></div>';
```

---

### Empty State

```php
Components::empty_state(string $title, array $options = []): string
```

| Option | Type | Description |
|---|---|---|
| `desc` | string | Description text |
| `action` | string | HTML for a call-to-action button |

```php
echo Components::empty_state(
    'No documents yet',
    [
        'desc'   => 'Documents appear here once WooCommerce orders are completed and DTEs are generated.',
        'action' => Components::button('View Orders', 'secondary', [
            'href' => admin_url('edit.php?post_type=shop_order'),
        ]),
    ]
);
```

---

### Breadcrumbs

```php
Components::breadcrumbs(array $items): string
```

Each item: `['label' => string, 'url' => string]`. The last item is rendered as current (no link).

```php
echo Components::breadcrumbs([
    ['label' => 'Bsale DTE',  'url' => admin_url('admin.php?page=bsale-dte')],
    ['label' => 'Settings',   'url' => admin_url('admin.php?page=bsale-dte-settings')],
    ['label' => 'Offices'],   // No URL = current page
]);
```

---

### Callout

A stronger, in-content emphasis block — use for important notes within documentation or settings pages.

```php
Components::callout(string $title, string $type = 'info', string $body = ''): string
```

**Types:** `info` `success` `warning` `danger` `accent`

```php
echo Components::callout(
    'Before going live',
    'warning',
    'Test your full order flow in sandbox first. Switch to production only after confirming
     boletas and facturas generate correctly and webhook events are received.'
);

echo Components::callout(
    'Pro feature: Multi-office routing',
    'accent',
    'Map WooCommerce shipping methods to different Bsale offices for document issuance.'
);
```

---

### Key-Value List

Displays structured metadata — ideal for document details, order meta, or API response previews.

```php
Components::kv_list(array $pairs): string
```

Values can contain HTML (badges, links, code). Keys are always escaped.

```php
echo Components::kv_list([
    'Order ID'     => '#' . $order->get_id(),
    'Bsale ID'     => esc_html($doc['bsale_document_id'] ?? '—'),
    'Document type'=> 'Boleta <code>(codeSii 39)</code>',
    'Status'       => Components::badge('Issued', 'dot-success'),
    'Attempts'     => esc_html($doc['attempts']),
    'Created'      => '<span class="wads-font-mono" style="font-size:12px">'
                      . esc_html($doc['created_at'])
                      . '</span>',
]);
```

---

## CSS-Only Components

These components are pure HTML + CSS. No PHP helper is needed — just copy the markup pattern.

---

### Tabs

```html
<!-- Tab nav: add data-wads-tabs to activate JS switching -->
<div class="wads-tabs" data-wads-tabs>
    <button class="wads-tab is-active" data-tab="tab-general">General</button>
    <button class="wads-tab"           data-tab="tab-offices">Offices</button>
    <button class="wads-tab"           data-tab="tab-webhooks">Webhooks</button>
</div>

<!-- Panel container must immediately follow the tab nav -->
<div class="wads-tab-panels">
    <div class="wads-tab-panel is-active" id="tab-general">
        <!-- General settings content -->
    </div>
    <div class="wads-tab-panel" id="tab-offices">
        <!-- Offices content -->
    </div>
    <div class="wads-tab-panel" id="tab-webhooks">
        <!-- Webhooks content -->
    </div>
</div>
```

**Rules:**
- `data-tab` on the button must match the `id` on the panel
- The panel container must be the **immediate next sibling** of the tab nav, or use `data-wads-panels="{nav-id}"` if they're separated
- Add `is-active` to both the default tab and its panel on initial render

---

### Table

```html
<div class="wads-table-wrap">
    <table class="wads-table">
        <thead>
            <tr>
                <th>Order</th>
                <th>Status</th>
                <th class="text-right">Actions</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>#1055</strong></td>
                <td><?php echo Components::badge('Issued', 'dot-success'); ?></td>
                <td class="text-right">
                    <button class="wads-btn wads-btn--ghost wads-btn--sm">View PDF</button>
                </td>
            </tr>
        </tbody>
    </table>
</div>
```

**Modifiers:**
- `.wads-table--striped` — alternating row background
- `.wads-table--compact` — reduced cell padding

---

### Accordion

```html
<div class="wads-accordion">
    <div class="wads-accordion-item">
        <button class="wads-accordion-trigger" aria-expanded="false">
            Section title
            <!-- Chevron icon (inline SVG or text "▾") -->
            <svg class="wads-accordion-trigger__icon" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2">
                <path d="m6 9 6 6 6-6"/>
            </svg>
        </button>
        <div class="wads-accordion-panel">
            <div class="wads-accordion-panel__inner">
                Content here.
            </div>
        </div>
    </div>
</div>
```

**To open by default**, add `aria-expanded="true"` to the trigger and `is-open` to the panel.

---

### Dropdown

```html
<div class="wads-dropdown">
    <button class="wads-btn wads-btn--secondary wads-btn--sm" data-wads-dropdown>
        Actions ▾
    </button>
    <div class="wads-dropdown__menu wads-dropdown__menu--right">
        <span class="wads-dropdown__label">Document</span>
        <a class="wads-dropdown__item" href="#">View PDF</a>
        <a class="wads-dropdown__item" href="#">Copy public URL</a>
        <div class="wads-dropdown__divider"></div>
        <a class="wads-dropdown__item wads-dropdown__item--danger" href="#">Void</a>
    </div>
</div>
```

**Modifiers:**
- `.wads-dropdown__menu--right` — align menu to right edge of trigger
- `.wads-dropdown__menu--up` — open upward

---

### Modal

```html
<!-- Trigger -->
<button class="wads-btn wads-btn--secondary" data-wads-modal-open="my-modal">
    Open modal
</button>

<!-- Modal — place anywhere in the page, outside of any overflow:hidden container -->
<div class="wads-modal-backdrop is-hidden" id="my-modal" data-wads-modal>
    <div class="wads-modal">

        <div class="wads-modal__header">
            <h3 class="wads-modal__title">Confirm action</h3>
            <button class="wads-modal__close" aria-label="Close">×</button>
        </div>

        <div class="wads-modal__body">
            <p>Are you sure you want to void document #3421?</p>
        </div>

        <div class="wads-modal__footer">
            <button class="wads-btn wads-btn--ghost" data-wads-modal-close>Cancel</button>
            <button class="wads-btn wads-btn--danger">Void document</button>
        </div>

    </div>
</div>
```

**Sizes:** `.wads-modal--sm` (380px) | default (540px) | `.wads-modal--lg` (720px)

**Closing:** Click backdrop, click `.wads-modal__close`, click any `[data-wads-modal-close]`, or press Escape.

---

### Timeline

```html
<div class="wads-timeline">

    <div class="wads-timeline-item wads-timeline-item--success">
        <div class="wads-timeline-item__header">
            <span class="wads-timeline-item__title">Document issued</span>
            <span class="wads-timeline-item__time">14:22:03</span>
        </div>
        <div class="wads-timeline-item__body">
            Boleta #3421 generated and sent to SII.
        </div>
    </div>

    <div class="wads-timeline-item wads-timeline-item--danger">
        <div class="wads-timeline-item__header">
            <span class="wads-timeline-item__title">First attempt failed</span>
            <span class="wads-timeline-item__time">11:05:42</span>
        </div>
        <div class="wads-timeline-item__body">
            API timeout. Queued for retry.
        </div>
    </div>

</div>
```

**Dot colors:** `wads-timeline-item--success` `--warning` `--danger` `--accent` _(or default neutral)_

---

### Steps / Wizard

```html
<div class="wads-steps">

    <div class="wads-step is-done">
        <div class="wads-step__circle">✓</div>
        <span class="wads-step__label">API Token</span>
    </div>

    <div class="wads-step is-active">
        <div class="wads-step__circle">2</div>
        <span class="wads-step__label">Office Setup</span>
    </div>

    <div class="wads-step">
        <div class="wads-step__circle">3</div>
        <span class="wads-step__label">Go Live</span>
    </div>

</div>
```

**States:** `is-done` (filled amber, checkmark) | `is-active` (ring, accent text) | _(default)_ (empty, muted)

---

### Pagination

```html
<nav class="wads-pagination">
    <span class="wads-page-item is-disabled">← Prev</span>
    <a class="wads-page-item is-active" href="?paged=1">1</a>
    <a class="wads-page-item" href="?paged=2">2</a>
    <a class="wads-page-item" href="?paged=3">3</a>
    <span class="wads-page-item wads-page-item--ellipsis">…</span>
    <a class="wads-page-item" href="?paged=18">18</a>
    <a class="wads-page-item" href="?paged=2">Next →</a>
</nav>
```

---

### Search Bar

```html
<div class="wads-search">
    <span class="wads-search__icon">
        <!-- Search icon SVG -->
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="8"/>
            <path d="m21 21-4.35-4.35"/>
        </svg>
    </span>
    <input class="wads-search__input" type="search"
           placeholder="Search by order or folio…"
           name="s">
    <button class="wads-search__clear" aria-label="Clear">×</button>
</div>
```

The clear button shows/hides automatically via JS when the input has a value.

---

### Copy Block

Displays a code block with a one-click copy button.

```html
<div class="wads-copy-block">
    <button class="wads-copy-block__btn" data-wads-copy="<?php echo esc_attr($code); ?>">
        Copy
    </button>
    <pre><code><?php echo esc_html($code); ?></code></pre>
</div>
```

The `data-wads-copy` attribute value is what gets copied to the clipboard. The button label temporarily changes to "Copied" for 1.5 seconds after a successful copy.

---

## Design Tokens Reference

All tokens are CSS custom properties on `:root`. Override them in your plugin's admin CSS to customize the system.

### Colors

```css
--wads-color-bg             /* Page background:  #F8F7F4 */
--wads-color-surface        /* Card background:  #FFFFFF */
--wads-color-surface-alt    /* Subtle surface:   #F3F1EC */
--wads-color-border         /* Default border:   #E2DDD5 */
--wads-color-border-strong  /* Emphasis border:  #C8C2B8 */

--wads-color-text           /* Primary text:     #1A1714 */
--wads-color-text-2         /* Secondary text:   #5A544D */
--wads-color-text-muted     /* Muted text:       #9A9088 */
--wads-color-text-on-accent /* Text on accent:   #FFFFFF */

--wads-color-accent         /* Brand / primary:  #9B6E35 */
--wads-color-accent-hover   /* Hover state:      #7F5828 */
--wads-color-accent-active  /* Active/pressed:   #683F10 */
--wads-color-accent-light   /* Tinted bg:        #F6EDD8 */
--wads-color-accent-ring    /* Focus ring:       rgba(155,110,53,.22) */

--wads-color-success        /* #3A7058 */
--wads-color-success-light  /* #EAF4EE */
--wads-color-success-text   /* #2A5240 */

--wads-color-warning        /* #8A6B1E */
--wads-color-warning-light  /* #FDF4DC */
--wads-color-warning-text   /* #6B5010 */

--wads-color-danger         /* #8B3535 */
--wads-color-danger-light   /* #FAEBEB */
--wads-color-danger-text    /* #6A2828 */
```

### Typography

```css
--wads-font-display  /* 'Lora', Georgia, serif */
--wads-font-body     /* 'Plus Jakarta Sans', -apple-system, sans-serif */
--wads-font-mono     /* 'JetBrains Mono', 'Fira Code', monospace */

--wads-text-xs:   11px
--wads-text-sm:   13px
--wads-text-base: 14px
--wads-text-md:   15px
--wads-text-lg:   18px
--wads-text-xl:   22px
--wads-text-2xl:  28px
--wads-text-3xl:  36px
```

### Spacing

```css
--wads-sp-1:  4px    --wads-sp-6:  24px
--wads-sp-2:  8px    --wads-sp-8:  32px
--wads-sp-3:  12px   --wads-sp-10: 40px
--wads-sp-4:  16px   --wads-sp-12: 48px
--wads-sp-5:  20px   --wads-sp-16: 64px
```

### Border Radius

```css
--wads-radius-sm:   3px
--wads-radius:      5px
--wads-radius-md:   7px
--wads-radius-lg:   10px
--wads-radius-xl:   16px
--wads-radius-full: 9999px
```

### Shadows

```css
--wads-shadow-sm  /* Subtle: cards at rest */
--wads-shadow     /* Default: elevated cards */
--wads-shadow-md  /* Medium: dropdowns, popovers */
--wads-shadow-lg  /* Heavy: modals */
```

---

## Layout Utilities

```html
<!-- Grid -->
<div class="wads-grid wads-grid--2">...</div>  <!-- 2 columns -->
<div class="wads-grid wads-grid--3">...</div>  <!-- 3 columns -->
<div class="wads-grid wads-grid--4">...</div>  <!-- 4 columns -->

<!-- Stack (vertical flex with gap) -->
<div class="wads-stack">...</div>           <!-- gap: 16px -->
<div class="wads-stack wads-stack--sm">...</div>  <!-- gap: 8px -->
<div class="wads-stack wads-stack--lg">...</div>  <!-- gap: 24px -->

<!-- Cluster (horizontal wrapping flex) -->
<div class="wads-cluster">...</div>

<!-- Space-between row -->
<div class="wads-between">
    <span>Left content</span>
    <span>Right content</span>
</div>
```

All grid layouts collapse to a single column on screens narrower than 768px.

---

## Best Practices

### Do: Wrap once, at the page level

```php
// ✅ Good — one wrapper, everything inside
function my_plugin_render_page(): void {
    echo '<div class="wads">';
    echo Components::page_header('My Plugin Settings');
    echo Components::settings_section([...]);
    echo '</div>';
}
```

```php
// ❌ Bad — multiple wrappers create unnecessary nesting
echo '<div class="wads">' . Components::page_header('Title') . '</div>';
echo '<div class="wads">' . Components::settings_section([...]) . '</div>';
```

### Do: Use Components for structure, raw HTML for content

```php
// ✅ Good — structure via Components, content via esc_html
echo Components::card([
    'title' => 'Recent Events',
    'body'  => '<p>' . esc_html($last_event_message) . '</p>',
]);
```

### Do: Always escape dynamic values

The `body`, `footer`, `header_end`, and `actions` slots in Components accept **raw HTML**. Escape everything before passing in:

```php
// ✅ Correct
echo Components::notice(
    'Order <strong>' . esc_html('#' . $order_id) . '</strong> processed.',
    'success'
);

// ❌ XSS risk
echo Components::notice("Order #{$order_id} processed.", 'success');
```

### Do: Use the App Shell only for complex plugins

The App Shell adds a fixed sidebar and a full-height layout. It's powerful but opinionated. Use it when your plugin has 4+ distinct admin pages. For simpler plugins, use flat `.wads` with tabs.

### Do: Enqueue conditionally

Only load the design system on your own plugin pages to avoid adding load to unrelated screens:

```php
add_action('admin_enqueue_scripts', function(string $hook): void {
    // Only on this plugin's pages
    if (!str_starts_with($hook, 'my-plugin')) return;

    DesignSystem::enqueue(DesignSystem::assets_url(__FILE__));
});
```

### Do: Manage state with `is-active`, not inline styles

```php
// ✅ Correct — use the active class
$nav_class = ($current_tab === 'api') ? 'wads-nav-item is-active' : 'wads-nav-item';
echo "<a class=\"{$nav_class}\" href=\"?page=my-plugin&tab=api\">API</a>";

// ❌ Avoid inline style overrides
echo "<a class=\"wads-nav-item\" style=\"color: red\">...</a>";
```

### Do: Keep nonces and security outside the design system

The design system handles presentation only. Nonces, capability checks, and input sanitization are your plugin's responsibility:

```php
// Security: your plugin's job
if (!current_user_can('manage_options')) {
    wp_die(esc_html__('Not authorized', 'my-plugin'));
}

// Presentation: design system's job
echo Components::settings_section([...]);
```

### Avoid: Overriding component internals

Instead of targeting `.wads-btn--primary` in your own CSS, use CSS custom properties to adjust the system to your brand:

```css
/* ✅ Good — use the token system */
.wads { --wads-color-accent: #2E6B4F; }

/* ❌ Fragile — tied to internal selectors */
.wads .wads-btn--primary { background: #2E6B4F !important; }
```

---

## Customization

Override any design token by redefining it on `.wads` in your plugin's admin stylesheet. This confines the change to your plugin's pages:

```css
/* my-plugin/assets/css/admin.css */

.wads {
    /* Change accent color for your brand */
    --wads-color-accent:       #2E6B4F;
    --wads-color-accent-hover: #1F5239;
    --wads-color-accent-light: #E0F0E8;
    --wads-color-accent-ring:  rgba(46, 107, 79, 0.2);

    /* Tighten border radius */
    --wads-radius:    3px;
    --wads-radius-md: 5px;
    --wads-radius-lg: 7px;
    --wads-radius-xl: 10px;

    /* Use system fonts instead of Google Fonts */
    --wads-font-display: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    --wads-font-body:    -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}
```

> **Important:** Only override on `.wads`, never on `:root`. This prevents your changes from leaking into other plugins that may also use this design system on the same page.

---

## Demo Files

Two HTML files are included to preview all components without a WordPress installation:

| File | Description |
|---|---|
| `demo.html` | All components in a flat reference layout — tokens, typography, forms, notices, tables, modals, and more |
| `demo-shell.html` | Full App Shell layout — sidebar, topbar, stats, search, table, pagination — simulating a real plugin admin page |

Open them directly in a browser (no server needed).

---

## Changelog

### 1.0.0
- Initial release
- CSS design system with 33 component sections
- PHP `Components` class with 18 helper methods
- Dark sidebar + App Shell layout
- Accordion, Dropdown, Tabs, Modal (JS-powered)
- Search, Chips, Pagination, Breadcrumbs
- Timeline, Steps/Wizard, Callout, Key-Value List
- Copy-to-clipboard, Copy Block

---

## License

This package is licensed under the **GNU General Public License v2.0 or later (GPL-2.0-or-later)** — the same license as WordPress itself.

This is intentional: WordPress.org [requires](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/) all plugins and their bundled dependencies to use a GPL-compatible license. Declaring `GPL-2.0-or-later` directly removes any ambiguity during plugin reviews and aligns with WordPress ecosystem standards.

### Third-party assets

| Asset | License | Compatible? |
|---|---|---|
| [Lora](https://fonts.google.com/specimen/Lora) (Google Fonts) | SIL Open Font License 1.1 | ✅ GPL-compatible |
| [Plus Jakarta Sans](https://fonts.google.com/specimen/Plus+Jakarta+Sans) (Google Fonts) | SIL Open Font License 1.1 | ✅ GPL-compatible |
| [JetBrains Mono](https://fonts.google.com/specimen/JetBrains+Mono) (Google Fonts) | SIL Open Font License 1.1 | ✅ GPL-compatible |

Fonts are loaded via `@import` from Google Fonts CDN — no font files are bundled in this package.
