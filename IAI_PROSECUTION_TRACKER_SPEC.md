# IAI Prosecution Fee Tracker — WordPress Plugin
## Project Specification & Architecture Document
### For use with GitHub Copilot Task Mode (Claude Opus 4.6)

---

## 1. Project Overview

A WordPress plugin for **Innovation Access Initiative** (innovationaccess.org) that allows users to search for patent applicants by name, retrieve their patent applications from the USPTO Open Data Portal (ODP) API, and display a visual timeline of prosecution fee payment events and entity status changes for each application.

**Hosted on:** SiteGround (WordPress, innovationaccess.org)
**Repository:** https://github.com/Synpathub/IAI
**Plugin directory:** `iai-prosecution-tracker/`
**Delivery:** Single WordPress plugin + WPCode snippets for page integration

---

## 2. Naming Conventions (USE THESE EVERYWHERE)

| Element | Convention |
|---------|-----------|
| Plugin slug | `iai-prosecution-tracker` |
| Text domain | `iai-prosecution-tracker` |
| PHP namespace | `IAI\ProsecutionTracker` |
| DB table prefix | `iai_pt_` |
| REST namespace | `iai/v1` |
| PHP constants prefix | `IAI_PT_` |
| CSS class prefix | `iai-pt-` |
| Shortcode | `[iai_prosecution_tracker]` |
| wp-config constant | `IAI_PT_USPTO_API_KEY` |
| Options prefix | `iai_pt_` |

---

## 3. Business Rules

### 3.1 Input — Applicant Name Search

- User enters an applicant name in a search box
- Boolean search operators:
  - `+` = AND (e.g., `Electronics + Telecommunications` matches names containing both words)
  - `*` = Wildcard (e.g., `Samsung*` matches "Samsung Electronics", "Samsung Display", etc.)
- Search results show matching applicant name variants from the USPTO database
- User can **select multiple name variants** (checkboxes) to account for typos/variations in the USPTO data (e.g., "Electronics and Telecommunications Research Inst", "Electronics & Telecommunications Research Institute", "ETRI")
- After selecting name variants, user clicks "Fetch Applications" to retrieve all applications across selected names

### 3.2 Output — Application List & Timeline

- **Left panel / vertical list:** All patent applications matching the selected applicant name(s), showing:
  - Application number
  - Filing date
  - Patent number (if granted)
  - Invention title (if available)
  - Current status

- **Main panel / timeline:** When an application is selected from the list, display a horizontal timeline showing:
  - **Entity status change events** (`BIG.`, `SMAL`, `MICR`) as colored status markers
  - **Fee payment events** (`FEE.`, `FLFEE`, `ADDFLFEE`, `IFEE`, etc.) as icons along the timeline
  - **RCE events** (`BRCE`, `FRCE`) as distinct markers
  - **Appeal events** (`AP.B`, `AP.C`, `APOH`) as distinct markers
  - **Key prosecution milestones** (`COMP`, `CTNF`, `CTFR`, `IFEE`) for context
  - Each event shows: date, code, description on hover/click
  - Entity status "zones" — colored background bands showing which entity rate was active during each period

---

## 4. Architecture

### 4.1 High-Level Architecture

```
┌─────────────────────────────────────────────────────────┐
│  WordPress Frontend (React via wp-scripts)              │
│  ┌─────────────┐  ┌──────────────────────────────────┐  │
│  │ Search Panel │  │ Timeline Visualization (main)    │  │
│  │ + Name List  │  │ - SVG timeline                   │  │
│  │ + App List   │  │ - Entity status color bands      │  │
│  │              │  │ - Fee event icons                 │  │
│  │              │  │ - Hover/click detail popups       │  │
│  └──────┬───────┘  └──────────────┬───────────────────┘  │
│         │                         │                      │
│         └────────┬────────────────┘                      │
│                  │ WP REST API (internal)                 │
│         ┌────────▼────────────┐                          │
│         │ Plugin PHP Backend  │                          │
│         │ - REST endpoints    │                          │
│         │ - USPTO API proxy   │                          │
│         │ - Caching layer     │                          │
│         │ - Query builder     │                          │
│         └────────┬────────────┘                          │
│                  │                                        │
│         ┌────────▼────────────┐                          │
│         │ WordPress DB Cache  │                          │
│         │ (custom tables)     │                          │
│         └────────┬────────────┘                          │
└──────────────────┼──────────────────────────────────────┘
                   │ HTTPS (server-side only)
          ┌────────▼────────────┐
          │ USPTO ODP API       │
          │ api.uspto.gov       │
          │ (API key on server) │
          └─────────────────────┘
```

### 4.2 Plugin File Structure

```
iai-prosecution-tracker/
├── iai-prosecution-tracker.php           # Main plugin file (bootstrap)
├── readme.txt                            # WP plugin readme
├── uninstall.php                         # Cleanup on uninstall
│
├── includes/
│   ├── class-plugin.php                  # Plugin singleton / loader
│   ├── class-activator.php               # DB table creation on activation
│   ├── class-deactivator.php             # Cleanup on deactivation
│   │
│   ├── api/
│   │   ├── class-rest-controller.php     # WP REST API endpoints
│   │   ├── class-uspto-client.php        # USPTO ODP API client (server-side)
│   │   └── class-query-builder.php       # Translates user search → USPTO query
│   │
│   ├── cache/
│   │   └── class-cache-manager.php       # DB caching for API responses
│   │
│   ├── models/
│   │   ├── class-application.php         # Application data model
│   │   ├── class-transaction.php         # Transaction/event data model
│   │   └── class-fee-classifier.php      # Classifies transaction codes
│   │
│   └── admin/
│       ├── class-settings-page.php       # Admin settings (API key, etc.)
│       └── views/
│           └── settings-page.php         # Settings page template
│
├── assets/
│   ├── src/                              # Source (compiled by wp-scripts)
│   │   ├── index.js                      # React app entry point
│   │   ├── App.jsx                       # Main app component
│   │   ├── components/
│   │   │   ├── SearchPanel.jsx           # Applicant search + name selection
│   │   │   ├── ApplicationList.jsx       # Vertical list of applications
│   │   │   ├── Timeline.jsx              # Main timeline visualization
│   │   │   ├── TimelineEvent.jsx         # Individual event on timeline
│   │   │   ├── EntityStatusBar.jsx       # Colored entity status bands
│   │   │   ├── EventDetailPopup.jsx      # Hover/click detail popup
│   │   │   └── LoadingSpinner.jsx        # Loading state
│   │   ├── hooks/
│   │   │   ├── useSearch.js              # Search API hook
│   │   │   ├── useApplications.js        # Fetch applications hook
│   │   │   └── useTransactions.js        # Fetch transactions hook
│   │   ├── utils/
│   │   │   ├── feeClassifier.js          # Client-side fee code classification
│   │   │   ├── queryParser.js            # Parse boolean search input
│   │   │   └── formatters.js             # Date/number formatters
│   │   └── styles/
│   │       ├── main.css                  # Global styles
│   │       ├── timeline.css              # Timeline-specific styles
│   │       └── variables.css             # CSS custom properties
│   │
│   └── build/                            # Compiled output (generated)
│       ├── index.js
│       ├── index.asset.php
│       └── style-index.css
│
├── templates/
│   └── shortcode-output.php              # Shortcode container template
│
└── package.json                          # Node dependencies
```

### 4.3 WPCode Snippets (for page integration)

**Snippet 1 — Page Layout Override (CSS, Site Wide Footer)**
Full-width layout override for the page containing the tracker.

**Snippet 2 — Shortcode Enhancement (PHP, Everywhere)**
Dequeue conflicting theme scripts/styles on tracker page.

**Snippet 3 — Access Control (PHP, Everywhere)**
Optional login requirement and role restrictions.

---

## 5. Database Schema

### 5.1 Cache Tables

```sql
-- Cached search results (applicant name → application numbers)
CREATE TABLE {prefix}iai_pt_search_cache (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    search_query VARCHAR(500) NOT NULL,
    query_hash CHAR(32) NOT NULL,
    result_data LONGTEXT NOT NULL,
    fetched_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,
    INDEX idx_query_hash (query_hash),
    INDEX idx_expires (expires_at)
);

-- Cached transaction histories per application
CREATE TABLE {prefix}iai_pt_transaction_cache (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    application_number VARCHAR(20) NOT NULL,
    transaction_data LONGTEXT NOT NULL,
    fetched_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,
    UNIQUE INDEX idx_app_number (application_number),
    INDEX idx_expires (expires_at)
);

-- Saved applicant name groups (user can save name variant sets)
CREATE TABLE {prefix}iai_pt_saved_searches (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    search_label VARCHAR(255) NOT NULL,
    name_variants TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_user (user_id)
);
```

### 5.2 Cache TTL Strategy

| Data Type | Default TTL | Rationale |
|-----------|-------------|-----------|
| Search results | 24 hours | Applications don't change frequently |
| Transaction history | 7 days | Prosecution events are append-only |
| Status codes | 30 days | Rarely change |

---

## 6. API Design — Internal WP REST Endpoints

All endpoints under namespace `iai/v1/`:

### 6.1 Search Applicants

```
POST /wp-json/iai/v1/search
Body: {
    "query": "Electronics + Telecommunications*",
    "limit": 50,
    "offset": 0
}
Response: {
    "total": 234,
    "applicant_names": [
        { "name": "Electronics and Telecommunications Research Institute", "count": 187 },
        { "name": "Electronics and Telecommunications Research Inst", "count": 12 },
        { "name": "ETRI", "count": 35 }
    ]
}
```

### 6.2 Fetch Applications for Selected Names

```
POST /wp-json/iai/v1/applications
Body: {
    "applicant_names": [
        "Electronics and Telecommunications Research Institute",
        "Electronics and Telecommunications Research Inst"
    ],
    "limit": 100,
    "offset": 0
}
Response: {
    "total": 199,
    "applications": [
        {
            "application_number": "16123456",
            "filing_date": "2018-03-15",
            "patent_number": "US11234567",
            "title": "METHOD FOR WIRELESS COMMUNICATION",
            "status": "Patented Case",
            "applicant_name": "Electronics and Telecommunications Research Institute"
        }
    ]
}
```

### 6.3 Fetch Transactions for an Application

```
GET /wp-json/iai/v1/transactions/{application_number}
Response: {
    "application_number": "16123456",
    "events": [
        {
            "date": "2018-03-15",
            "code": "SMAL",
            "description": "Small Entity Status - Verified Statement Filed",
            "category": "entity_status",
            "entity_rate": "small",
            "is_fee_event": false,
            "is_entity_change": true
        }
    ],
    "entity_status_timeline": [
        { "from": "2018-03-15", "to": "2022-06-01", "status": "small" },
        { "from": "2022-06-01", "to": null, "status": "undiscounted" }
    ]
}
```

---

## 7. Fee Classifier — Transaction Code Taxonomy

```php
const CATEGORIES = [
    'entity_status' => [
        'BIG.'  => ['label' => 'Large Entity (Undiscounted)', 'icon' => 'building', 'color' => '#DC2626'],
        'SMAL'  => ['label' => 'Small Entity', 'icon' => 'store', 'color' => '#2563EB'],
        'MICR'  => ['label' => 'Micro Entity', 'icon' => 'user', 'color' => '#059669'],
    ],
    'filing_fee' => [
        'FEE.'     => ['label' => 'Fee Payment', 'icon' => 'dollar-sign', 'color' => '#7C3AED'],
        'FLFEE'    => ['label' => 'Additional Filing Fee', 'icon' => 'dollar-sign', 'color' => '#7C3AED'],
        'ADDFLFEE' => ['label' => 'Additional Filing Fees', 'icon' => 'dollar-sign', 'color' => '#7C3AED'],
    ],
    'issue_fee' => [
        'IFEE'   => ['label' => 'Issue Fee Paid', 'icon' => 'award', 'color' => '#D97706'],
        'IFEEHA' => ['label' => 'Issue Fee (Hague)', 'icon' => 'award', 'color' => '#D97706'],
    ],
    'rce' => [
        'BRCE' => ['label' => 'RCE Filed', 'icon' => 'refresh-cw', 'color' => '#EC4899'],
        'FRCE' => ['label' => 'RCE Complete', 'icon' => 'check-circle', 'color' => '#EC4899'],
    ],
    'appeal' => [
        'AP.B' => ['label' => 'Appeal Brief Filed', 'icon' => 'file-text', 'color' => '#F59E0B'],
        'AP.C' => ['label' => 'Pre-Appeal Conference', 'icon' => 'users', 'color' => '#F59E0B'],
        'APOH' => ['label' => 'Oral Hearing Request', 'icon' => 'mic', 'color' => '#F59E0B'],
    ],
    'milestone' => [
        'COMP'    => ['label' => 'Application Complete', 'icon' => 'check', 'color' => '#6B7280'],
        '371COMP' => ['label' => '371 National Stage Complete', 'icon' => 'globe', 'color' => '#6B7280'],
        'CTNF'    => ['label' => 'Non-Final Rejection', 'icon' => 'x-circle', 'color' => '#EF4444'],
        'CTFR'    => ['label' => 'Final Rejection', 'icon' => 'x-octagon', 'color' => '#B91C1C'],
        'DIST'    => ['label' => 'Terminal Disclaimer', 'icon' => 'scissors', 'color' => '#6B7280'],
        'ABN6'    => ['label' => 'Abandoned (No Issue Fee)', 'icon' => 'alert-triangle', 'color' => '#991B1B'],
    ],
    'other_fee' => [
        'IRFND'  => ['label' => 'Refund Requested', 'icon' => 'rotate-ccw', 'color' => '#6B7280'],
        'EIDS.'  => ['label' => 'Electronic IDS', 'icon' => 'list', 'color' => '#6B7280'],
        'M923'   => ['label' => '371 Supplemental Fees Missing', 'icon' => 'alert-circle', 'color' => '#DC2626'],
    ],
];
```

This taxonomy is defined in one place (`class-fee-classifier.php`) and mirrored in `feeClassifier.js`. Adding new codes requires editing only these two files.

---

## 8. Frontend Component Wireframes

### 8.1 SearchPanel.jsx

```
┌──────────────────────────────────────┐
│ Search Applicant Names               │
│ ┌──────────────────────────────────┐ │
│ │ Electronics + Telecom*        🔍 │ │
│ └──────────────────────────────────┘ │
│                                      │
│ ☑ Electronics and Telecommunications │
│   Research Institute (187)           │
│ ☑ Electronics and Telecommunications │
│   Research Inst (12)                 │
│ ☐ Electronics and Telecomm Research  │
│   Institute (3)                      │
│ ☑ ETRI (35)                          │
│                                      │
│ [Select All] [Clear]                 │
│ [ 🔄 Fetch Applications ]            │
└──────────────────────────────────────┘
```

### 8.2 ApplicationList.jsx

```
┌──────────────────────────────────────┐
│ Applications (199)     [Sort ▼]      │
│ ┌──────────────────────────────────┐ │
│ │ ► 16/123,456          2018-03-15│ │
│ │   US 11,234,567   Patented Case │ │
│ │   METHOD FOR WIRELESS COMM...   │ │
│ ├──────────────────────────────────┤ │
│ │   16/234,567          2019-01-10│ │
│ │   Pending         Non-Final OA  │ │
│ │   APPARATUS FOR SIGNAL PROC...  │ │
│ └──────────────────────────────────┘ │
│ [◄ Prev]  Page 1 of 4  [Next ►]     │
└──────────────────────────────────────┘
```

### 8.3 Timeline.jsx

```
┌────────────────────────────────────────────────────────────────────┐
│ Application 16/123,456 — METHOD FOR WIRELESS COMMUNICATION        │
│ Filed: 2018-03-15  |  Patent: US 11,234,567  |  Status: Patented │
│                                                                    │
│ Entity Status:                                                     │
│ ██████████ Small ██████████████████████|██ Large ██████████████    │
│                                                                    │
│ Timeline:                                                          │
│  2018       2019       2020       2021       2022       2023      │
│  │          │          │          │          │          │          │
│  🏪─$──📋──$──✖──$──✖──🔄─$──✖──🏢─$──🏆──              │
│                                                                    │
│ Legend: 🏪=Small 🏢=Large $=Fee 🔄=RCE ✖=Rejection 🏆=Issue     │
│                                                                    │
│ [Filter: ☑ Fees ☑ Entity ☑ Milestones ☑ RCE ☑ Appeals]          │
└────────────────────────────────────────────────────────────────────┘
```

---

## 9. WPCode Snippets

### Snippet 1 — Page Layout Override (CSS, Site Wide Footer)

```css
body.page-slug-prosecution-tracker .entry-content,
body.page-slug-prosecution-tracker .site-content {
    max-width: 100%;
    padding: 0 20px;
}
body.page-slug-prosecution-tracker .sidebar {
    display: none;
}
body.page-slug-prosecution-tracker .content-area {
    width: 100%;
    max-width: 100%;
}
```

### Snippet 2 — Shortcode Enhancement (PHP, Everywhere)

```php
add_filter('the_content', function($content) {
    if (has_shortcode($content, 'iai_prosecution_tracker')) {
        wp_dequeue_style('theme-sidebar-widgets');
    }
    return $content;
});
```

### Snippet 3 — Access Control (PHP, Everywhere)

```php
add_filter('iai_pt_require_login', '__return_true');
add_filter('iai_pt_allowed_roles', function($roles) {
    return ['administrator', 'editor', 'iai_analyst'];
});
```

---

## 10. SiteGround Deployment Notes

### 10.1 Environment

- **Site:** innovationaccess.org
- **PHP Version:** 8.1+ (SiteGround supports this)
- **WordPress:** 6.4+
- **SSL:** Required (SiteGround provides free Let's Encrypt)

### 10.2 API Key Storage

```php
// In wp-config.php:
define('IAI_PT_USPTO_API_KEY', 'your-api-key-here');
```

### 10.3 SiteGround Caching Bypass

```php
// In REST endpoint handlers:
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-SG-Cache-Bypass: 1');
```

### 10.4 Cron for Cache Cleanup

```php
register_activation_hook(__FILE__, function() {
    if (!wp_next_scheduled('iai_pt_cache_cleanup')) {
        wp_schedule_event(time(), 'daily', 'iai_pt_cache_cleanup');
    }
});

add_action('iai_pt_cache_cleanup', function() {
    global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->prefix}iai_pt_search_cache WHERE expires_at < NOW()");
    $wpdb->query("DELETE FROM {$wpdb->prefix}iai_pt_transaction_cache WHERE expires_at < NOW()");
});
```

---

## 11. Copilot Task Mode — Prompt Sequence

Use these prompts in order with GitHub Copilot Task mode (Claude Opus 4.6). Each task targets the `iai-prosecution-tracker/` directory.

### Task 1: Plugin Bootstrap & Database Tables
```
Create the WordPress plugin bootstrap and activation files for the IAI Prosecution Fee Tracker.

Files to create inside iai-prosecution-tracker/:
- iai-prosecution-tracker.php (main plugin file — already exists, verify/update it)
- includes/class-plugin.php (singleton loader)
- includes/class-activator.php (creates DB tables on activation)
- includes/class-deactivator.php (cleanup on deactivation)
- uninstall.php (drop tables on uninstall)

The activator must create three database tables using $wpdb and dbDelta():
1. {prefix}iai_pt_search_cache (id, search_query, query_hash CHAR(32), result_data LONGTEXT, fetched_at, expires_at)
2. {prefix}iai_pt_transaction_cache (id, application_number VARCHAR(20) UNIQUE, transaction_data LONGTEXT, fetched_at, expires_at)
3. {prefix}iai_pt_saved_searches (id, user_id, search_label, name_variants TEXT, created_at, updated_at)

Use namespace IAI\ProsecutionTracker. Constants prefix IAI_PT_. Text domain: iai-prosecution-tracker.
See PATENTRACK_PROSECUTION_TRACKER_SPEC.md sections 2 and 5 for full details.
```

### Task 2: USPTO API Client
```
Create includes/api/class-uspto-client.php in the iai-prosecution-tracker plugin.

This is a PHP class (namespace IAI\ProsecutionTracker\API) that wraps the USPTO Open Data Portal API.

Methods:
- search_applicants(string $query, int $limit = 50, int $offset = 0): array
  Calls POST https://api.uspto.gov/patent/v1/applications/search with facets on firstApplicantName.
  Returns array of ['name' => string, 'count' => int] for distinct applicant name variants.

- get_applications(array $applicant_names, int $limit = 100, int $offset = 0): array
  Builds an OR query across multiple applicant names.
  Returns array of application objects with: applicationNumberText, filingDate, patentNumber, inventionTitle, applicationStatusDescriptionText, firstApplicantName.

- get_transactions(string $application_number): array
  Calls GET https://api.uspto.gov/patent/v1/{applicationNumberText}/transactions
  Returns raw transaction event array.

API key from: defined('IAI_PT_USPTO_API_KEY') ? IAI_PT_USPTO_API_KEY : get_option('iai_pt_api_key')
Use wp_remote_post / wp_remote_get. Timeout: 30 seconds. Return WP_Error on failure.
Add x-api-key header and Content-Type: application/json.
```

### Task 3: Query Builder
```
Create includes/api/class-query-builder.php in the iai-prosecution-tracker plugin.

Namespace IAI\ProsecutionTracker\API. This class translates user search input into USPTO ODP query syntax.

Rules:
- + means AND
- * means wildcard
- Bare words are wrapped in wildcards for partial matching
- Multiple terms joined by + are combined with AND

Examples:
- "Samsung*" → firstApplicantName:(Samsung*)
- "Electronics + Telecommunications" → firstApplicantName:(*Electronics* AND *Telecommunications*)
- "Electronics + Telecom*" → firstApplicantName:(*Electronics* AND Telecom*)
- "ETRI" → firstApplicantName:(*ETRI*)

Method: build_applicant_query(string $user_input): string
Also: build_multi_name_query(array $exact_names): string — for fetching applications by exact selected names, using OR.
```

### Task 4: Fee Classifier
```
Create includes/models/class-fee-classifier.php in the iai-prosecution-tracker plugin.

Namespace IAI\ProsecutionTracker\Models. This class classifies USPTO transaction codes.

Use the taxonomy from PATENTRACK_PROSECUTION_TRACKER_SPEC.md section 7.

Methods:
- classify(string $event_code): array|null — returns ['category', 'label', 'icon', 'color'] or null if unknown
- is_fee_event(string $event_code): bool
- is_entity_change(string $event_code): bool
- compute_entity_timeline(array $events): array — walks events chronologically, tracks BIG./SMAL/MICR changes, returns array of {from, to, status} periods
- get_entity_rate_at_date(array $timeline, string $date): string — returns 'small', 'micro', or 'undiscounted'
- get_all_codes(): array — returns full taxonomy

The taxonomy must be defined as a class constant so it's easy to update.
```

### Task 5: Cache Manager
```
Create includes/cache/class-cache-manager.php in the iai-prosecution-tracker plugin.

Namespace IAI\ProsecutionTracker\Cache.

Methods:
- get_search(string $query_hash): array|null
- set_search(string $query_hash, string $query, array $data, int $ttl_hours = 24): void
- get_transactions(string $app_number): array|null
- set_transactions(string $app_number, array $data, int $ttl_hours = 168): void
- purge_expired(): int — deletes expired rows, returns count deleted
- clear_all(): void — truncates both cache tables

All methods use $wpdb with prepare(). Table names use $wpdb->prefix . 'iai_pt_'.
Check if fetched_at + ttl > now when reading. Return null on miss or expired.
```

### Task 6: REST Controller
```
Create includes/api/class-rest-controller.php in the iai-prosecution-tracker plugin.

Namespace IAI\ProsecutionTracker\API. Registers WP REST API routes under namespace 'iai/v1'.

Routes:
1. POST /iai/v1/search — body: {query, limit, offset}
   → Check cache (hash of normalized query) → if miss, call USPTO_Client::search_applicants → cache result → return
2. POST /iai/v1/applications — body: {applicant_names[], limit, offset}
   → Call USPTO_Client::get_applications → return (no cache needed, these are aggregated)
3. GET /iai/v1/transactions/(?P<app_number>\d+) — URL param
   → Check cache → if miss, call USPTO_Client::get_transactions → run through Fee_Classifier → cache → return

All endpoints:
- permission_callback: verify nonce OR check if user is logged in (based on iai_pt_require_login option)
- Add headers: Cache-Control: no-store and X-SG-Cache-Bypass: 1
- Return WP_REST_Response with proper status codes
- Sanitize all inputs with sanitize_text_field / absint
```

### Task 7: Admin Settings
```
Create includes/admin/class-settings-page.php and includes/admin/views/settings-page.php
in the iai-prosecution-tracker plugin.

Settings page under Settings menu → "IAI Prosecution Tracker".

Fields:
- USPTO API Key (password input, option: iai_pt_api_key) — note: wp-config.php constant IAI_PT_USPTO_API_KEY takes precedence
- Search Cache TTL in hours (number, default 24, option: iai_pt_search_cache_ttl)
- Transaction Cache TTL in hours (number, default 168, option: iai_pt_transaction_cache_ttl)
- Require Login (checkbox, option: iai_pt_require_login)
- "Clear All Cache" button that calls purge

Use WordPress Settings API. Namespace IAI\ProsecutionTracker\Admin.
Show a notice if API key is not set in either wp-config.php or options.
```

### Task 8: Frontend — Package Setup & Entry Point
```
Update package.json in iai-prosecution-tracker/ and create the React entry point.

Create:
- assets/src/index.js — renders <App /> into #iai-prosecution-tracker using wp.element
- assets/src/App.jsx — main component with two-panel layout:
  Left sidebar (350px): SearchPanel + ApplicationList
  Main area: Timeline (or welcome message if no app selected)
  Use CSS grid or flexbox. Manage state: selectedNames, applications, selectedApp, transactions.
  Use wp.apiFetch for API calls (automatically handles nonce).

Pass these via wp_localize_script in the plugin:
- iaiPT.restUrl (rest_url('iai/v1/'))
- iaiPT.nonce (wp_create_nonce('wp_rest'))
```

### Task 9: Frontend — SearchPanel Component
```
Create assets/src/components/SearchPanel.jsx in the iai-prosecution-tracker plugin.

Features:
- Text input with placeholder "Search applicant names (use + for AND, * for wildcard)"
- Search button (or Enter key) triggers API call to /iai/v1/search
- Results appear as checkbox list: each row shows applicant name + application count in parentheses
- Select All / Clear All buttons
- "Fetch Applications" button — calls onFetchApplications(selectedNames) prop
- Loading state with spinner during search
- Error handling with user-friendly messages

Props: onFetchApplications(names: string[])
Use wp.apiFetch. CSS class prefix: iai-pt-search-
```

### Task 10: Frontend — ApplicationList Component
```
Create assets/src/components/ApplicationList.jsx in the iai-prosecution-tracker plugin.

Props: applications: array, selectedApp: string|null, onSelectApp(appNumber): void, loading: boolean

Features:
- Scrollable vertical list (max-height with overflow-y: auto)
- Each card shows: application number (formatted as XX/XXX,XXX), filing date, patent number (if any), title (truncated to 60 chars), status
- Selected card highlighted with blue left border
- Clicking a card calls onSelectApp
- Pagination: 25 per page, prev/next buttons
- Sort dropdown: by filing date (newest first default), by status
- Shows total count header: "Applications (199)"
- Loading state
- Empty state: "No applications found"

CSS class prefix: iai-pt-apps-
```

### Task 11: Frontend — Timeline Component
```
Create assets/src/components/Timeline.jsx and supporting sub-components in iai-prosecution-tracker plugin.

Also create: TimelineEvent.jsx, EntityStatusBar.jsx, EventDetailPopup.jsx

This is the main visualization. Build with pure React + inline SVG (no external charting library).

Timeline.jsx:
- Receives: events array, entity_status_timeline array, application metadata
- Renders horizontal SVG timeline with:
  - Date axis (year markers)
  - EntityStatusBar: colored horizontal bands behind events (blue=#2563EB for small, red=#DC2626 for large, green=#059669 for micro)
  - TimelineEvent icons positioned by date, using lucide-react icons based on fee classifier
  - Category filter checkboxes (Fees, Entity Status, Milestones, RCE, Appeals) — toggling hides/shows events
- Horizontally scrollable for long prosecution histories
- Application header showing: app number, title, filing date, patent number, status

TimelineEvent.jsx:
- Renders a single icon on the timeline
- onClick/onHover shows EventDetailPopup

EventDetailPopup.jsx:
- Positioned near clicked event
- Shows: date, code, description, category, entity rate at that date
- Close button

Use the color/icon mapping from the fee classifier taxonomy.
CSS class prefix: iai-pt-timeline-
```

### Task 12: Frontend — Styles
```
Create CSS files in iai-prosecution-tracker/assets/src/styles/:

variables.css:
- CSS custom properties for all colors from fee classifier taxonomy
- Font sizes, spacing scale, border radius
- Sidebar width (350px), breakpoints

main.css:
- Two-column layout: sidebar fixed 350px, main area fills rest
- Card styles for application list
- Form input styles matching innovationaccess.org design (clean, professional)
- Responsive: stack to single column below 768px

timeline.css:
- SVG timeline container (overflow-x: auto)
- Entity status band styles
- Event icon positioning and hover effects
- Detail popup positioning and styling
- Filter checkbox row
- Legend styling

All classes prefixed with iai-pt-. Keep it clean and professional — this is for innovationaccess.org.
```

### Task 13: Shortcode Registration & Script Enqueue
```
In iai-prosecution-tracker/includes/class-plugin.php, add:

1. Shortcode registration: [iai_prosecution_tracker]
   - Renders a container: <div id="iai-prosecution-tracker" class="iai-pt-container"></div>
   - Only enqueues scripts/styles when shortcode is present on the page

2. Script/style enqueue:
   - Enqueue assets/build/index.js with dependencies from assets/build/index.asset.php
   - Enqueue assets/build/style-index.css
   - wp_localize_script with iaiPT object containing restUrl and nonce
   - Only load on pages with the shortcode (use has_shortcode check or a flag)

3. Register the REST routes by instantiating Rest_Controller on rest_api_init
4. Register the admin settings page on admin_menu
5. Register the cache cleanup cron
```

---

## 12. Extension Points (Future-Proofing)

| Future Feature | Where to Add |
|----------------|-------------|
| Maintenance fee tracking | New fee category in classifier + timeline event type |
| PDF export of timeline | New REST endpoint + server-side PDF generation |
| Bulk applicant comparison | New React component consuming existing API |
| Assignment data overlay | New USPTO client method + timeline layer |
| Fee amount computation | New model class using published fee schedules |
| Multi-user saved searches | Already supported via user_id in saved_searches table |
| PatentsView integration | New API client class, same REST controller pattern |

---

## 13. Security

- USPTO API key stored server-side only (never exposed to frontend)
- All REST endpoints verify WordPress nonce
- Input sanitization on all user search queries
- SQL queries use `$wpdb->prepare()` exclusively
- Rate limiting on internal REST endpoints (configurable)
- Optional login requirement via filter
- XSS prevention: all output escaped with `esc_html()` / `esc_attr()`

---

## 14. Testing Checklist

- [ ] Search with `+` operator returns correct AND results
- [ ] Search with `*` wildcard returns matching name variants
- [ ] Multiple name selection aggregates applications correctly
- [ ] Transaction timeline renders for an application with entity status changes
- [ ] Entity status color bands correctly reflect BIG./SMAL/MICR transitions
- [ ] Fee events appear at correct positions on timeline
- [ ] Cache hit returns same data without API call
- [ ] Cache expiry triggers fresh API call
- [ ] Admin settings save and load correctly
- [ ] Plugin activation creates database tables
- [ ] Plugin deactivation/uninstall cleans up properly
- [ ] Shortcode renders on a WordPress page
- [ ] SiteGround caching doesn't interfere with REST responses
- [ ] Works on mobile (responsive layout)
