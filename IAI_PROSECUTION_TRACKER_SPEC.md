# IAI Prosecution Fee Tracker — Project Specification

See the full architecture specification, database schema, API design, fee classifier taxonomy,
frontend component wireframes, and Copilot task prompts in this document.

## Quick Reference

- **Plugin directory:** `iai-prosecution-tracker/`
- **Plugin slug:** `iai-prosecution-tracker`
- **PHP namespace:** `IAI\ProsecutionTracker`
- **REST namespace:** `iai/v1`
- **DB table prefix:** `iai_pt_`
- **Shortcode:** `[iai_prosecution_tracker]`
- **Target site:** innovationaccess.org (SiteGround)

## Architecture

WordPress plugin with:
- PHP backend: REST API endpoints that proxy to USPTO Open Data Portal API
- React frontend: compiled via @wordpress/scripts, renders prosecution fee timeline
- DB caching: custom tables for caching USPTO API responses
- Admin settings: API key configuration, cache TTL controls

## Build

```bash
cd iai-prosecution-tracker
npm install
npm run build
```

Frontend assets auto-build via GitHub Actions on push to assets/src/.
