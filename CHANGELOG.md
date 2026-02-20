# Changelog

All notable changes to Adeptus Insights will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.0] - 2026-02-20

### Added
- **Scheduled Reports** - Automated email delivery of reports on daily, weekly, or monthly schedules with PDF generation support
- **Cohort & Group Filters** - Multi-select dropdown filters in report UI with capability-aware SQL-level filtering
- **Teacher/Learner Dashboards** - Role-based auto-scoped dashboard views with mode indicators and new capabilities
- **Time Tracking Reports** - 5 new report templates tracking student learning time with dedicated time_calculator helper
- **Learner Self-Service Progress View** - Privacy-scoped dashboard showing completion, time spent, and grades
- **Teacher Performance Reports** - 4 new report templates for teacher analytics with teacher_metrics helper
- **Inactivity Alerts** - Weekly email digest identifying at-risk students (enterprise tier)
- **White-Label Branding** - Custom logo, colours, and footer text (enterprise tier)
- **Rule-Based Alert Triggers** - Flexible alert engine with custom trigger rules, admin UI for rule CRUD, and activity log viewer
- **Alert Triggers Admin UI** - Complete management interface for alert rules with form builder and log viewer
- **Moodle 5.x Compatibility** - Bootstrap 5 dual-attributes support, compatible with Moodle 4.1-5.0
- **Feature Gating** - Enterprise tier feature controls for advanced functionality

### Fixed
- **GH-26**: Registered missing external services for scheduled reports functionality
- **GH-26**: Fixed bookmark reportid column type mismatch
- **GH-27**: Resolved coding standard violations across G2-G9 feature implementations
- **GH-28**: Fixed PHP linting errors introduced in multi-feature sprint
- **CI**: Comprehensive coding standards and linting fixes

### Changed
- Enhanced report wizard with new categories for teacher analytics and time tracking
- Improved capability system with granular permissions for dashboards and reports
- Extended database schema for scheduled reports, alerts, and branding configuration

## [1.0.0] - 2025-XX-XX

### Added
- Initial release of Adeptus Insights
- Core reporting engine
- Report builder interface
- Basic analytics templates

[2.0.0]: https://github.com/brainycheeks/adeptus-insights/releases/tag/v2.0.0
[1.0.0]: https://github.com/brainycheeks/adeptus-insights/releases/tag/v1.0.0
