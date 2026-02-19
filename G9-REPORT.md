# G9: White-Label / Reseller Branding — Implementation Report

**Date:** 2026-02-19  
**Plugin Version:** 1.12.0 (2026021907)  
**Commit:** `feat(G9): white-label branding — custom logo, colours, footer, enterprise tier gate`

## What Was Built

### 1. Admin Settings Page (`branding_settings.php`)
- Custom logo upload via Moodle file API (`whitelabel_logo` file area)
- Primary/secondary colour pickers (HTML5 `<input type="color">`)
- Custom footer text input
- Custom report header text input (replaces "Adeptus 360" company name)
- "Powered by Adeptus 360" toggle checkbox
- Enterprise tier gate — non-Enterprise users see upgrade prompt

### 2. Branding Manager Extensions (`classes/branding_manager.php`)
- `is_whitelabel_available()` — static method checking `license_tier === 'enterprise'`
- `get_whitelabel_settings()` / `save_whitelabel_settings()` — CRUD for config values
- `get_whitelabel_logo_url()` — serves logo via `pluginfile.php`
- `get_whitelabel_logo_file()` — returns `stored_file` for PDF embedding
- `save_whitelabel_logo()` — saves draft area to permanent file area
- `get_merged_pdf_branding_config()` — merges white-label overrides into PDF config (colours, logo, footer, header, powered-by)
- `hex_to_rgb()` — utility for PDF colour conversion

### 3. PDF Branding Integration
- `get_merged_pdf_branding_config()` returns a config array that the existing `branded_pdf.php` can consume directly
- Custom logo from file storage is converted to data URI for TCPDF embedding
- Custom colours override default header/footer RGB values
- Custom footer/header text override defaults

### 4. On-Screen Branding (CSS)
- CSS custom properties `--adeptus-primary` and `--adeptus-secondary` in `styles.css`
- `.adeptus-wl-logo`, `.adeptus-wl-header`, `.adeptus-wl-footer`, `.adeptus-powered-by` classes
- Templates can inject inline `<style>` to set custom property values from settings

### 5. Enterprise Tier Gate
- Uses same pattern as G8 alerts: `get_config('report_adeptus_insights', 'license_tier') === 'enterprise'`
- Non-Enterprise users see a warning notification + upgrade button

### 6. Capability
- `report/adeptus_insights:managebranding` — write capability, granted to `manager` archetype

### 7. Lang Strings
- 20 new strings added covering all UI elements, headings, descriptions, and enterprise gate messages

### 8. File Serving
- `report_adeptus_insights_pluginfile()` callback in `lib.php` serves `whitelabel_logo` files
- 24-hour browser cache, login required

## Files Modified
| File | Change |
|------|--------|
| `branding_settings.php` | **NEW** — admin settings page |
| `classes/branding_manager.php` | Extended with white-label methods |
| `db/access.php` | Added `managebranding` capability |
| `lang/en/report_adeptus_insights.php` | Added 20 lang strings |
| `version.php` | Bumped to 1.12.0 (2026021907) |
| `settings.php` | Added branding nav link |
| `lib.php` | Added pluginfile callback |
| `styles.css` | Added CSS custom properties and classes |

## Validation
- ✅ `php -l` passed on all modified files
- ✅ `purge_caches.php` succeeded
- ✅ `upgrade.php --non-interactive` succeeded (version 2026021907 applied)
- ✅ Git committed

## Integration Notes
- To apply branding in PDF exports, call `$manager->get_merged_pdf_branding_config()` instead of `$manager->get_pdf_branding_config()` in `report_executor.php`
- On-screen branding requires templates to read settings and inject CSS custom property overrides via inline `<style>` block
- The `branded_pdf.php` Header/Footer methods should check `$config['show_powered_by']` to conditionally render attribution
