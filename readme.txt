=== kursflow Kursliste ===
Contributors: weilmaschinchen
Tags: courses, kursflow, booking, widgets, gutenberg
Requires at least: 6.0
Tested up to: 6.5
Stable tag: 0.1.1
Requires PHP: 7.4
License: GPL-2.0+
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Integrates your kursflow.de course listings seamlessly into WordPress.

== Description ==

The kursflow Kursliste plugin lets you display your courses and events from [kursflow.de](https://kursflow.de) directly on your WordPress site. Use the native Gutenberg block, a simple shortcode, or let the widget appear automatically in the footer. Configuration is easy via a dedicated settings page.

= Features =

* **Gutenberg Block** – Add a “kursflow Kursliste” block to any post or page.
* **Shortcode** – Use `[kursflow_kurse]` for classic editor compatibility.
* **Auto-Embed** – Let the widget appear automatically on every page (footer).
* **Settings Page** – Manage API key, tenant slug, cache TTL, layout, and more.
* **Cron Sync** (optional) – Cache course data locally for faster loading.
* **Secure** – API key never exposed to visitors; server-side communication only.

= Usage =

1. Go to **Settings → kursflow** and enter your API key and tenant slug (e.g., `meine-fahrschule`).
2. Use the “Test Connection” button to verify your credentials.
3. Insert the block or shortcode where you want your course list to appear.
4. Optionally enable the “Widget auf jeder Seite anzeigen” option.

= Shortcode Attributes =

`[kursflow_kurse slug="my-tenant" branche="yoga" limit="5" layout="grid"]`
* `slug` – (optional) Override tenant slug.
* `branche` – (optional) Filter by branch/category.
* `limit` – (optional) Maximum number of courses to display.
* `layout` – (optional) Choose between `liste`, `grid`, or `kompakt`.

= Security Note =

Your API key is stored in the WordPress database (similar to other plugins). We recommend standard WordPress hardening (strong passwords, regular updates, SSL) to keep your site secure. The key is never rendered in frontend output.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/kursflow-wordpress-plugin` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Configure your settings at **Settings → kursflow**.

== Frequently Asked Questions ==

= Where can I get an API key? =

You can create an API key in your kursflow.de tenant dashboard under “Einstellungen → API”.

= Can I use multiple tenants on one site? =

The current version supports one tenant per WordPress installation. Multi-tenant support is planned for a future release.

= Is the booking handled on my site? =

No. Bookings are still processed on your tenant’s kursflow.de subdomain for a seamless and secure experience.

== Screenshots ==

1. Settings page with API key, slug, and test button.
2. Gutenberg block inserter showing the kursflow block.
3. Frontend widget example.

== Changelog ==

= 0.1.1 =
* Add Gutenberg block editor script (editor.js) — InspectorControls für Branche, Limit, Layout
* Add block asset manifest (block.asset.php) mit wp-blocks/wp-block-editor/wp-components/wp-element/wp-i18n Dependencies
* Add editor.css + frontend.css für Block-Styling

= 0.1.0 =
* Initial release.

== Upgrade Notice ==

= 0.1.1 =
Editor-Block ist jetzt vollständig im Gutenberg-Editor verwendbar (vorher nur Settings + Shortcode).

= 0.1.0 =
Initial release.
