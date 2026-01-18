# Adeptus Insights - Administrator Guide

## Overview

This guide covers essential administration tasks for the Adeptus Insights plugin.

## Installation

### Requirements
- Moodle 4.1 or higher
- PHP 7.4 or higher (8.1+ recommended)
- HTTPS enabled

### Install Steps
1. Upload plugin via **Site administration > Plugins > Install plugins**
2. Follow installation prompts
3. Navigate to **Reports > Adeptus Insights**
4. Complete registration (enter admin name and email)
5. Select subscription plan

## Configuration

### Admin Settings
**Location:** Site administration > Plugins > Reports > Adeptus Insights

| Setting | Description | Default |
|---------|-------------|---------|
| Enable Email Notifications | Send alerts for subscription changes | Enabled |
| Notification Email | Email for system notifications | Empty |

### User Access
The plugin uses one capability: `report/adeptus_insights:view`

**Default access:**
- Managers: Granted
- Teachers: Granted
- Students: Not granted

**To modify access:**
1. Go to Site administration > Users > Permissions > Define roles
2. Edit the desired role
3. Set `report/adeptus_insights:view` to Allow or Prevent

## Subscription Management

### Viewing Subscription
Navigate to **Reports > Adeptus Insights > Subscription** to see:
- Current plan and status
- AI credits remaining
- Reports used vs limit
- Billing information

### Upgrading Plans
1. Go to Subscription tab
2. Select a higher plan
3. Complete payment via Stripe
4. Features activate immediately

### Downgrading/Cancelling
- **Downgrade warning:** Reports exceeding new limit are auto-deleted (oldest first)
- **Cancellation:** Access continues until billing period ends

### Credit Reset
- Free plan: Credits counted all-time
- Paid plans: Credits reset on billing date

## Data Security

### What Stays on Your Server
- All student data
- Course information
- Report results
- Grades and assessments

### What We Receive
- Admin email and name (registration)
- Site URL (license validation)
- AI query text only (not results)

**Important:** Student data and report results NEVER leave your server.

## Privacy API

The plugin implements Moodle's Privacy API for GDPR compliance:
- Users can export their data
- Users can request data deletion
- All user data is properly tracked and deletable

### Data Tables
| Table | Contains |
|-------|----------|
| adeptus_generated_reports | Saved reports |
| adeptus_report_history | Report generation log |
| adeptus_report_bookmarks | User bookmarks |
| adeptus_usage_tracking | Usage statistics |

## Troubleshooting

### Plugin Not Loading
1. Check user has `report/adeptus_insights:view` capability
2. Verify plugin is installed correctly
3. Check Moodle error logs

### AI Assistant Issues
1. Verify subscription is active
2. Check AI credits remaining
3. Test internet connectivity

### Export Problems
1. CSV/Excel/JSON require Pro plan or higher
2. PDF limited to 5,000 rows
3. Check browser popup blocker

## Maintenance

### Regular Tasks
- Monitor subscription status
- Review user access permissions
- Check error logs periodically

### Before Updates
1. Backup Moodle database
2. Note current plugin version
3. Review release notes

## Support

- **Email:** support@adeptus360.com
- **Website:** www.adeptus360.com
- **Documentation:** docs.adeptus360.com

---

*Adeptus Insights v1.0.0 | Moodle 4.1+*
