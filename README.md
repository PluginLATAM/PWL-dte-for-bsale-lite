# PWL DTE for Bsale (Lite)

WooCommerce plugin for Chile: generate **boleta** and **factura electrónica** via [Bsale](https://www.bsale.cl/) when orders complete.

**Stable release:** `2.0.6` · **WordPress.org:** [Plugin Directory](https://wordpress.org/plugins/pwl-dte-for-bsale/) (when listed)

---

## Why this repo looks different from `readme.txt`

WordPress.org expects a specific **`readme.txt`** format (headers like `===`, `== Section ==`, etc.). That file is **bundled in the plugin zip** and in local builds under `releases/lite/`, but it is **not tracked in Git** here so GitHub can show a normal **`README.md`** instead.

---

## Features (Lite)

- Automatic **boleta electrónica** (SII code 39) on order completion
- **Factura electrónica** when the customer requests an invoice (RUT + company data)
- RUT validation (classic + Blocks checkout)
- Manual stock sync from Bsale
- **`[pwl_dte]`** shortcode for thank-you / My Account
- Sandbox mode, activity logs, duplicate prevention via `referenceId`

**Pro** (separate product) adds cron stock sync, webhooks, credit notes, multi-office, etc.

---

## Requirements

- WordPress 6.0+
- WooCommerce 8.0+
- PHP 8.0+
- Bsale account and API token

---

## Installation

1. Install the plugin in `wp-content/plugins/pwl-dte-for-bsale/` (or from WordPress.org when available).
2. Activate under **Plugins**.
3. Go to **PWL DTE → Settings**, enter your token, choose Sandbox/Production and office.
4. Use **Test Connection** to verify.

---

## External services (summary)

The plugin calls the **Bsale API** to create DTEs and read stock. Order and billing data are sent when issuing documents; SKUs are used for stock sync. See the shipped **`readme.txt`** (WordPress.org format) for the full “External Services” disclosure required for directory review.

- API docs: [docs.bsale.dev](https://docs.bsale.dev/)
- Terms / privacy: links in `readme.txt` in the distributed package

---

## Source assets

Production JS/CSS live under `assets/`. The **editable sources** (`resources/`, Vite/Tailwind config) live in the main development repository used to produce this mirror; this public tree is the **distributable Lite** layout.

Build (in dev repo): `npm install` → `npm run build` / `npm run dev`.

---

## License

GPL-3.0-or-later. See `composer.json` / plugin header.

---

## Changelog (high level)

For the full, directory-style changelog, open **`readme.txt`** in the built plugin or the zip from WordPress.org.

Recent highlights:

- **2.0.6** — Plugin Check–oriented hardening, WC logger usage, DB/query and metadata fixes.
- **2.0.5** — WordPress.org review compliance (escaping, assets, i18n) and Lite E2E workflow.
- **2.0.0** — Rename to PWL DTE for Bsale; inline scripts via `wp_add_inline_script`; External Services readme section.
