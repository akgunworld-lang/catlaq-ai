=== Catlaq Online Expo ===
Contributors: catlaq
Requires at least: 6.6
Tested up to: 6.8.3
Requires PHP: 8.1
Stable tag: 0.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

B2B Digital Expo platform with AI-driven agreements, logistics, and documentation workflows.

== Description ==
Catlaq Online Expo unifies engagement-first onboarding, Digital Expo RFQs, agreement rooms, and premium expo booths under a single WordPress plugin. The first release focuses on the platform kernel:

* Modular folder layout for AI, Social, Digital Expo, Agreement, Logistics services.
* Autoloader, plugin bootstrap, and module registrar powering future feature work.
* Database schema installer (profiles, companies, RFQs, agreement rooms, documents, audit log).
* REST status endpoint at `/wp-json/catlaq/v1/status` to verify environment readiness.
* Admin menu (`Catlaq â†’ Dashboard / Settings`) wired to starter view templates.
* Frontend shortcodes `[catlaq_engagement_feed]`, `[catlaq_expo_booths]`, and `[catlaq_digital_expo_showcase]` with JS placeholders hitting `/profiles` and `/rfq` using localized REST nonce (`catlaqREST`).
* Membership utility shortcodes `[catlaq_membership_overview]`, `[catlaq_membership_plans]`, `[catlaq_auth_portal]`, and `[catlaq_policy type="privacy|terms|refund"]` so any page can expose Expo registration and policy content.
* Settings page backed by the WordPress Settings API (environment, AI provider, escrow key, payment routing including WorldFirst fields).
* Membership billing REST/CLI scaffolding with invoice tracking + AI aware quotas, including checkout links via Mock, Stripe, Checkout.com, or WorldFirst providers.
* WP-CLI command `wp catlaq status` (mirrors the REST status check).
* New REST endpoints: `/profiles` (GET/POST), `/rfq` (GET/POST), `/agreements` (GET/POST) stubs for future integrations (agreement creation produces placeholder document files + invite tokens, all actions logged to audit table).
* Scheduled cron hook `catlaq_reminder_cron` (hourly) for future agreement reminders.
* Onboarding wizard page (`Catlaq â†’ Onboarding`) storing progress per user.

== Installation ==
1. Upload the plugin files to the `/wp-content/plugins/catlaq-online-expo` directory.
2. Activate the plugin through the "Plugins" screen in WordPress (activation creates Catlaq tables and capabilities).
3. Visit `/wp-json/catlaq/v1/status` to confirm all tables exist before enabling additional modules.
4. Use the `[catlaq_engagement_feed]` or `[catlaq_expo_booths]` shortcodes on any page to preview UI scaffolding.
5. Configure environment/provider details under `Catlaq â†’ Settings`.
6. Follow the Catlaq onboarding wizard in the admin area (coming in later releases).

== Frequently Asked ==
= Can I disable modules? =
Not yet. The current build ships structural scaffolding only; feature toggles land once modules are implemented.

= Does the plugin connect to external AI/escrow providers? =
No external APIs are called in this version. Integration points are stubbed so you can wire providers safely later.

= Where are the database tables defined? =
Check `includes/class-schema.php` for full SQL definitions used during activation.

== Changelog ==
= 0.2.0 =
* Added membership invoice storage plus REST endpoints to kick off checkout and review invoices per user.
* Introduced WorldFirst payment provider selection with partner/API fields that drive checkout URL generation.
* Extended payment gateway interface so every provider can return membership checkout URLs (used by the Digital Expo dashboard + AI runtime).

= 0.1.0 =
* Initial scaffolding: directory tree, module registrar, asset registration.
* Database schema + activation hooks.
* REST status endpoint for health checks.









