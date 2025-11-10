# Backend-Managed Subscription Data

## Overview

The Adeptus Insights plugin has been updated to remove local subscription data management and rely entirely on the backend API for all subscription-related operations.

## Changes Made

### 1. Removed Local Data Seeding
- **Removed**: `report_adeptus_insights_seed_default_data()` function
- **Removed**: Local Stripe plans seeding
- **Removed**: Local Stripe configuration seeding
- **Removed**: Local installation settings seeding

### 2. Removed Local Database Tables
- **Removed**: `adeptus_stripe_plans` table
- **Removed**: `adeptus_stripe_config` table
- **Kept**: `adeptus_subscription_status` (for local caching only)
- **Kept**: `adeptus_stripe_webhooks` (for webhook event tracking)
- **Kept**: `adeptus_usage_tracking` (for local usage tracking)

### 3. Updated API Methods
- **Updated**: `get_available_plans()` - Now gets plans from backend API
- **Updated**: `activate_free_plan()` - Now calls backend API
- **Updated**: `create_subscription()` - Already uses backend API
- **Updated**: All methods now send API key in header instead of request body

### 4. Updated Test and Diagnostic Scripts
- **Updated**: `test_subscription.php` - Removed local database queries
- **Updated**: `diagnostic.php` - Removed local table checks for plans
- **Added**: Better error handling and debugging

## Backend API Endpoints Used

### Subscription Management
- `POST /api/subscription/plans` - Get available subscription plans
- `POST /api/subscription/create` - Create new subscription
- `POST /api/subscription/activate-free` - Activate free plan
- `POST /api/subscription/status` - Check subscription status
- `POST /api/subscription/cancel` - Cancel subscription
- `POST /api/subscription/update` - Update subscription

### Configuration
- `GET /api/subscription/config` - Get Stripe configuration
- `POST /api/installation/register` - Register installation
- `POST /api/installation/verify` - Verify installation status

## Benefits of Backend Management

### 1. Centralized Data Management
- All subscription plans managed in one place
- Consistent pricing and features across all installations
- Easy to update plans without plugin updates

### 2. Better Security
- Stripe configuration not stored locally
- API keys managed securely on backend
- Reduced attack surface

### 3. Improved Maintenance
- No need to update plugin for plan changes
- Backend can manage complex subscription logic
- Better error handling and logging

### 4. Scalability
- Backend can handle complex business logic
- Easy to add new subscription features
- Better integration with external services

## Local Data Storage

The plugin still maintains some local data for performance and offline functionality:

### 1. Subscription Status Cache
- `adeptus_subscription_status` table
- Caches current subscription details
- Updated via webhooks and API calls
- Provides fast access to subscription info

### 2. Installation Settings
- `adeptus_install_settings` table
- Stores API key and connection details
- Required for plugin operation
- Minimal sensitive data

### 3. Usage Tracking
- `adeptus_usage_tracking` table
- Tracks local usage for reporting
- Helps with quota management
- Provides usage analytics

### 4. Webhook Events
- `adeptus_stripe_webhooks` table
- Tracks webhook events for debugging
- Prevents duplicate processing
- Audit trail for subscription changes

## Migration Notes

### For Existing Installations
1. **No data loss**: Local subscription status is preserved
2. **Automatic migration**: Plugin will fetch plans from backend on first access
3. **Backward compatibility**: Existing subscriptions continue to work

### For New Installations
1. **Clean slate**: No local data seeding
2. **API-first**: All data comes from backend
3. **Simplified setup**: Less local configuration required

## Testing

### 1. Run Diagnostic
```bash
https://your-moodle-site.com/report/adeptus_insights/diagnostic.php
```

### 2. Test Subscription Flow
```bash
https://your-moodle-site.com/report/adeptus_insights/test_subscription.php
```

### 3. Check API Connectivity
- Verify backend API is accessible
- Check API key authentication
- Test plan retrieval

## Troubleshooting

### Issue: "No plans available"
**Solution**: Check backend API connectivity and ensure plans are configured on backend

### Issue: "API key required"
**Solution**: Complete installation registration to get API key

### Issue: "Subscription creation failed"
**Solution**: Check backend logs and Stripe configuration

### Issue: "Database tables missing"
**Solution**: Reinstall plugin to create required local tables

## Future Enhancements

1. **Real-time sync**: WebSocket updates for subscription changes
2. **Offline mode**: Local caching for offline functionality
3. **Advanced analytics**: Backend-driven usage analytics
4. **Multi-tenant**: Support for multiple installations per organization

The move to backend-managed subscription data provides better scalability, security, and maintainability while preserving all existing functionality. 