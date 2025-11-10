# Adeptus Insights - Complete Installation Flow

## Overview

The Adeptus Insights plugin follows a streamlined installation process that ensures users are automatically set up with a free subscription and can immediately start using the plugin.

## Installation Flow

### 1. Plugin Installation
```
User installs plugin → Plugin creates database tables → Plugin adds menu entry → Redirect to settings
```

**Files involved:**
- `db/install.php` - Creates database tables and menu entry
- `db/install.xml` - Defines table structure
- `index.php` - Main entry point

### 2. First Access (Settings)
```
User accesses plugin → Check if registered → If not registered → Show registration form
```

**Files involved:**
- `index.php` - Checks registration status
- `subscription.php` - Registration form and subscription management

### 3. Registration Process
```
User fills registration form → Backend API registration → Auto-assign free subscription → Redirect to main dashboard
```

**Files involved:**
- `classes/installation_manager.php` - Handles registration
- `api_proxy.php` - Forwards requests to backend
- Backend API endpoints

### 4. Auto-Free Subscription
```
Registration successful → Get available plans from backend → Find free plan → Activate free subscription → Update local cache
```

**Files involved:**
- `classes/installation_manager.php` - `setup_starter_subscription()`
- Backend API: `subscription/plans` and `subscription/activate-free`

### 5. Main Dashboard
```
User sees main dashboard → Can view reports → Can manage subscription → Can upgrade plans
```

**Files involved:**
- `index.php` - Main dashboard
- `templates/index.mustache` - Dashboard template

## Expected Flow Sequence

### Step 1: Plugin Installation
1. **Admin installs plugin** via Moodle admin interface
2. **Plugin creates tables**:
   - `adeptus_install_settings`
   - `adeptus_subscription_status`
   - `adeptus_reports`
   - `adeptus_stripe_webhooks`
   - `adeptus_usage_tracking`
3. **Plugin adds menu entry** to custom user menu
4. **Plugin redirects to settings** page

### Step 2: First Access & Registration
1. **User accesses plugin** via menu or direct URL
2. **Plugin checks registration status**:
   - If not registered → Redirect to `subscription.php`
   - If registered → Show main dashboard
3. **Registration form** collects:
   - Admin name
   - Admin email
   - Site information (auto-detected)
4. **Backend registration**:
   - Creates installation record
   - Generates API key
   - Returns installation details

### Step 3: Auto-Free Subscription
1. **Registration successful** → Auto-trigger free subscription setup
2. **Get available plans** from backend API
3. **Find free plan** in available plans
4. **Activate free subscription** via backend API
5. **Update local cache** with subscription details
6. **Redirect to main dashboard**

### Step 4: Main Dashboard
1. **User sees dashboard** with:
   - Current subscription status
   - Available reports
   - Usage statistics
   - Subscription management options
2. **User can**:
   - View and run reports
   - Manage subscription
   - Upgrade/downgrade plans
   - View usage analytics

## API Endpoints Used

### Registration
- `POST /api/installation/register` - Register new installation
- `POST /api/installation/verify` - Verify installation status

### Subscription Management
- `GET /api/subscription/config` - Get payment configuration
- `POST /api/subscription/plans` - Get available plans
- `POST /api/subscription/activate-free` - Activate free plan
- `POST /api/subscription/create` - Create paid subscription
- `POST /api/subscription/status` - Check subscription status

### Reports
- `POST /api/installation/reports` - Get compatible reports
- `POST /api/reports/sync` - Sync reports from backend

## Database Tables

### Local Tables (Plugin Side)
1. **`adeptus_install_settings`**
   - Stores API key and connection details
   - Required for plugin operation

2. **`adeptus_subscription_status`**
   - Caches current subscription details
   - Updated via webhooks and API calls

3. **`adeptus_reports`**
   - Stores report definitions
   - Synced from backend based on Moodle version

4. **`adeptus_stripe_webhooks`**
   - Tracks webhook events
   - Prevents duplicate processing

5. **`adeptus_usage_tracking`**
   - Tracks local usage
   - Helps with quota management

### Backend Tables (API Side)
1. **`moodle_installations`**
   - Stores registered installations
   - Manages API keys

2. **`subscription_plans`**
   - Stores available plans
   - Managed centrally

3. **`subscriptions`**
   - Stores user subscriptions
   - Handles billing

4. **`stripe_config`**
   - Stores payment configuration
   - Managed securely

## Security Considerations

### 1. API Key Management
- API keys generated on backend
- Stored securely in local database
- Sent in headers for authentication

### 2. Payment Security
- No payment data stored locally
- All payment processing via backend
- Stripe configuration on backend only

### 3. Data Privacy
- Minimal sensitive data stored locally
- Most data managed on backend
- Local cache for performance only

## Error Handling

### 1. Registration Failures
- Show clear error messages
- Allow retry of registration
- Log detailed error information

### 2. API Connectivity Issues
- Graceful degradation
- Retry mechanisms
- Fallback to cached data

### 3. Subscription Issues
- Clear error messages
- Support contact information
- Debug information for troubleshooting

## Testing the Flow

### 1. Fresh Installation
```bash
# 1. Install plugin
# 2. Access plugin
# 3. Complete registration
# 4. Verify free subscription
# 5. Test main dashboard
```

### 2. Diagnostic Tools
```bash
# Run diagnostic
https://your-site.com/report/adeptus_insights/diagnostic.php

# Test subscription flow
https://your-site.com/report/adeptus_insights/test_subscription.php
```

### 3. Debug Information
- Enable Moodle debugging
- Check plugin logs
- Monitor API requests/responses

## Troubleshooting

### Common Issues

1. **"Installation not registered"**
   - Complete registration process
   - Check API connectivity

2. **"No plans available"**
   - Check backend plan configuration
   - Verify API connectivity

3. **"Payment configuration failed"**
   - Check backend payment setup
   - Verify API endpoints

4. **"Database tables missing"**
   - Reinstall plugin
   - Check database permissions

### Debug Steps

1. **Run diagnostic script**
2. **Check API connectivity**
3. **Verify registration status**
4. **Test subscription flow**
5. **Check backend logs**

## Future Enhancements

1. **Real-time updates** via WebSocket
2. **Offline mode** with local caching
3. **Advanced analytics** dashboard
4. **Multi-tenant** support
5. **Custom branding** options

The installation flow ensures a smooth, automated experience where users are quickly set up with a free subscription and can immediately start using the plugin's features. 