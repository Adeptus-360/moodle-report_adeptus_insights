# Adeptus Insights — Technology Stack
Last updated: 2026-02-12

## Architecture
Moodle report plugin — AI-powered analytics and report generation.

## Runtime
- PHP: >= 7.4 (8.1+ recommended)
- Moodle: 4.1+ (2022112800)

## Dependencies
- PHPMailer: 7.x (email functionality via Composer)
- Moodle core libraries (not separately managed)

## Features
- AI Assistant for SQL report generation
- Report Wizard (step-by-step interface)
- Subscription management
- Export: PDF, CSV, JSON
- Chart visualizations

## Package Manager
- Composer (PHP dependencies)

## Hosting
- Deployed as Moodle plugin on Adeptus360 Moodle instance
- Protected by Cloudflare Access (service token auth)
- Domain: moodle-adeptus360.davidmorake.com

## Security Notes
- Runs within Moodle's security context
- Cloudflare Access protection layer
- AI endpoint handles SQL generation (injection risk — review needed)
- API proxy for external AI service calls
