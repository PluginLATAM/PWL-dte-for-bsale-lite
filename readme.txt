=== PWL DTE for Bsale ===
Contributors: userdm
Tags: woocommerce, bsale, electronic invoicing, chile, dte
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 2.0.0
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Automatically generate electronic tax documents (boletas and facturas) via Bsale when orders are completed in WooCommerce.

== Description ==

**PWL DTE for Bsale** connects your WooCommerce store with [Bsale](https://www.bsale.cl/), the leading electronic invoicing and point-of-sale platform in Chile. When an order is completed, the plugin automatically generates the corresponding tax document (boleta or factura electrónica) and submits it to the SII.

= Key Features =

* **Automatic boleta electrónica** — generated when an order is completed (codeSii 39)
* **Factura electrónica** — customer checks "I need an invoice" at checkout and provides their RUT and company name
* **Real-time RUT validation** — check digit verification with instant feedback
* **Classic and Blocks checkout compatible** — works with WooCommerce shortcode checkout and the new Blocks checkout (WC 8.6+)
* **Manual stock sync** — update WooCommerce stock from Bsale with one click
* **Duplicate prevention** — uses referenceId to avoid issuing the same DTE twice
* **`[pwl_dte]` shortcode** — display the tax document on the order confirmation page or My Account
* **Sandbox mode** — test without affecting real SII documents
* **Activity logs** — detailed record of every DTE issued with status and folio number

= Requirements =

* WordPress 6.0 or higher
* WooCommerce 8.0 or higher
* PHP 8.0 or higher
* Active [Bsale](https://www.bsale.cl/) account with API token

= Lite vs Pro =

The free (Lite) edition includes boleta, factura, checkout fields, manual stock sync, and DTE shortcode.

The Pro version adds: automatic cron-based stock sync, dedicated stock office, multi-office support (shipping method → office mapping), automatic retry for failed DTEs, real-time webhooks, and credit notes on refunds.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/pwl-dte-for-bsale/` or install it directly from the WordPress plugin directory.
2. Activate the plugin from the **Plugins** menu in WordPress.
3. Go to **PWL DTE → Settings** and enter your Bsale API token.
4. Select the mode (Sandbox or Production) and the issuing office.
5. Click **Test Connection** to verify the token is valid.
6. Done! DTEs will be generated automatically when orders are completed.

== Frequently Asked Questions ==

= Do I need a Bsale account? =

Yes. This plugin requires an active [Bsale](https://www.bsale.cl/) account and an API token with permission to issue documents.

= Does it work in sandbox mode? =

Yes. In **Settings → Connection** you can enable Sandbox mode to test without issuing real documents to the SII.

= What happens if DTE generation fails? =

The error is recorded in **PWL DTE → DTE Logs** with the Bsale error message. You can retry manually from the order metabox in the WooCommerce admin.

= Is it compatible with WooCommerce Blocks checkout? =

Yes. The RUT, Company Name, and Business Activity fields work with both the classic checkout ([woocommerce_checkout]) and the Blocks checkout (WC 8.6+).

= Does the plugin deduct stock in Bsale? =

Yes, if the option is enabled in settings. You can control this via the dispatch parameter in the document settings.

= Where can I see generated DTEs? =

Each WooCommerce order has a "Bsale — Tax Document" metabox showing the document type, folio, status, and links to the PDF and public URL. You can also view the full history in **PWL DTE → DTE Logs**.

== External Services ==

This plugin connects to the [Bsale API](https://www.bsale.cl/) to generate and manage electronic tax documents (DTEs) required by Chilean tax law (SII).

= What data is sent and when =

* When a WooCommerce order is completed, the order details (products, quantities, prices, and customer billing information such as RUT and company name) are sent to Bsale to create a tax document (boleta or factura electrónica).
* When manual stock sync is triggered by the store admin, product SKUs are sent to retrieve current stock levels from Bsale.
* When testing the API connection from the settings page, a simple request is made to verify the token is valid. No customer data is sent.

= Service information =

* Bsale website: [https://www.bsale.cl/](https://www.bsale.cl/)
* Bsale API documentation: [https://docs.bsale.dev/](https://docs.bsale.dev/)
* Terms of service: [https://www.bsale.cl/terminos-y-condiciones](https://www.bsale.cl/terminos-y-condiciones)
* Privacy policy: [https://www.bsale.cl/politica-de-privacidad](https://www.bsale.cl/politica-de-privacidad)

== Screenshots ==

1. Plugin settings — Connection tab
2. Checkout with electronic invoice fields
3. "Bsale — Tax Document" metabox on the order
4. DTE Logs page with filters

== Changelog ==

= 2.0.0 =
* Renamed plugin to PWL DTE for Bsale (slug: pwl-dte-for-bsale) for WordPress.org trademark compliance.
* Converted all inline scripts to use wp_add_inline_script() per WordPress best practices.
* Added External Services section to readme documenting Bsale API usage.

= 1.0.5 =
* Renamed plugin to Bsale DTE (slug: bsale-dte) for WordPress.org compliance.

= 1.0.1 =
* Fix: In Blocks checkout (WC Blocks), the "I need an electronic invoice" field is now read correctly using the _wc_other/ prefix that WooCommerce Blocks uses internally. This fixes an issue where a Boleta was always generated instead of a Factura when using the Blocks checkout.

= 1.0.0 =
* Initial public release.
* Automatic boleta and factura electrónica generation on order completion.
* RUT, Company Name, and Business Activity fields in checkout (classic and Blocks).
* Real-time RUT validation via AJAX.
* Manual stock sync from Bsale.
* DTE shortcode to display the document on the frontend.
* Sandbox mode for testing.
* Activity logs with filters.

== Upgrade Notice ==

= 2.0.0 =
Plugin renamed to PWL DTE for Bsale. All internal identifiers updated for WordPress.org trademark compliance.
