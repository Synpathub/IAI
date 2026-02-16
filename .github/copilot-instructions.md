# Copilot Instructions — IAI Prosecution Fee Tracker

## Project Context

This is a WordPress plugin for Innovation Access Initiative (innovationaccess.org) that
queries the USPTO Open Data Portal (ODP) API to retrieve patent prosecution fee payment
data and displays it on a visual timeline. It is hosted on SiteGround.

The plugin lives in the `iai-prosecution-tracker/` directory within this repository.

## Architecture Rules

1. **Single plugin, no external PHP dependencies.** Use only WordPress core functions
   (wp_remote_get, $wpdb, WP REST API, wp-scripts for frontend build).

2. **React frontend compiled via @wordpress/scripts.** Source in `assets/src/`, build
   output in `assets/build/`. Never edit build files directly.

3. **USPTO API key is server-side only.** The frontend communicates with the plugin's
   internal WP REST API endpoints, which proxy requests to the USPTO API. Never expose
   the API key to the browser.

4. **All database queries use `$wpdb->prepare()`.** No exceptions.

5. **Cache everything.** Every USPTO API response is cached in custom DB tables with
   configurable TTLs. Check cache before making any external API call.

6. **Fee classification is centralized.** Transaction code → category/icon/color mapping
   lives in `class-fee-classifier.php` (PHP) and `feeClassifier.js` (JS). These two
   files must stay in sync. When adding new codes, update both.

7. **Entity status is determined from transaction codes, NOT from metadata.**
   The `entityStatus` field on the application metadata is the *current* status and
   is unreliable for historical analysis. Always derive entity rate from the most
   recent BIG./SMAL/MICR event in the transaction history.

## Naming Conventions

- Plugin slug: `iai-prosecution-tracker`
- Text domain: `iai-prosecution-tracker`
- PHP namespace: `IAI\ProsecutionTracker`
- DB table prefix: `iai_pt_`
- REST namespace: `iai/v1`
- PHP constants prefix: `IAI_PT_`
- CSS class prefix: `iai-pt-`
- Shortcode: `[iai_prosecution_tracker]`

## USPTO ODP API Reference

- Base URL: `https://api.uspto.gov/patent/v1`
- Auth: `x-api-key` header
- Search: `POST /patent/applications/search` (JSON body with q, filters, fields, limit, offset, facets)
- Transactions: `GET /patent/{applicationNumberText}/transactions`
- Documents: `GET /patent/{applicationNumberText}/documents`
- Covers applications filed on or after January 1, 2001

## Coding Standards

- PHP: WordPress Coding Standards (WPCS)
- JavaScript: ESLint with @wordpress/eslint-plugin
- CSS: BEM naming with `iai-pt-` prefix, CSS custom properties for theming
- React: Functional components with hooks, no class components
- Use lucide-react for icons

## Key Transaction Codes

Entity status: BIG. (large/undiscounted), SMAL (small), MICR (micro)
Filing fees: FEE., FLFEE, ADDFLFEE
Issue fee: IFEE
RCE: BRCE, FRCE
Appeals: AP.B, AP.C, APOH
Milestones: COMP, 371COMP, CTNF, CTFR, DIST
Abandonment: ABN6, ABNF

## SiteGround Specifics

- Add `X-SG-Cache-Bypass: 1` header to all REST responses
- API key in wp-config.php: `define('IAI_PT_USPTO_API_KEY', '...')`
- PHP 8.1+, WordPress 6.4+
