# Analenzor User Behavior Analytics
افزونه اتصال وردپرس به آنالنزور برای تحلیل رفتار کاربران و بازپخش نشست‌ه.
WordPress integration for Analenzor user behavior analytics and session replay.

## Links

- Website: https://analenzor.com/
- Dashboard: https://dashboard.analenzor.com/
- WordPress plugin: https://wordpress.org/plugins/analenzor-user-behavior-analytics/
- Privacy: https://dashboard.analenzor.com/privacy
- Terms: https://dashboard.analenzor.com/terms

## Features

- Enable or disable tracking and configure a website tracking ID.
- Asynchronously load the external Analenzor tracking script.
- Persian/English settings interface with RTL support.
- WooCommerce cart, checkout, and authorized order-state integration.

Reports are available in the external dashboard, not inside WordPress. An Analenzor account is required. The settings status is not a connectivity test.

## Installation

For ordinary use, install the official WordPress directory release. For this source package:

1. Put these files inside `wp-content/plugins/analenzor-user-behavior-analytics/`.
2. Activate the plugin in WordPress.
3. Open the Analenzor settings page and enter your website tracking ID, not a script or URL.
4. Review privacy and consent requirements before enabling tracking.
5. Save settings, clear page caches, and check reports in the dashboard.

GitHub's automatically generated source ZIP may use a different folder name; rename the extracted folder as above.

## Privacy and external service

The remote tracking script is not included here. When enabled with a tracking ID, it loads from `https://dashboard.analenzor.com/api/script/`. Browser IP address and request headers are visible to that service. The external script controls analytics/session replay collection.

Signed-in usernames and first roles are made available to the remote script. WooCommerce integration reads cart state and authorized order state through the site's AJAX endpoint, exposes commerce state in browser JavaScript, and stores session state in `sessionStorage` under `analenzor_c`. Do not assume sessions are anonymous.

This plugin does not include a consent banner or automatic consent-manager integration. Site owners must review disclosures, applicable requirements, and consent before enabling it. See `readme.txt` for details.

## Requirements and testing

Declared minimums: WordPress 5.0 and PHP 7.2. These are inherited declarations, not newly verified compatibility guarantees. Test on staging, including guest and logged-in sessions, WooCommerce checkout/payment flows, page caching, and tracking disabled/enabled. No PHP/WordPress runtime test was performed for this packaging revision.

## GitHub packaging revision

Based on supplied version 1.0.12. Internal version synchronized, documentation added, and an unverified `Tested up to` claim removed. The bundled custom font was removed because redistribution rights were not supplied; system fonts are used instead. Tracking and commerce logic were not changed. This package is not represented as an official WordPress directory update.

## Contributing and security

Open issues with reproduction steps and environment versions. Do not post credentials, tracking account secrets, customer data, order keys, or recordings. Report suspected vulnerabilities privately using the official contact channel on the Analenzor website.

## License

Plugin code: GNU GPL version 2 or later; see `LICENSE`. The supplied artwork is retained; the publisher should confirm rights to redistribute it before public release. No rights to the Analenzor trademark are granted by this code license.
