# Subscription Onboarding Issues - Fixes Applied

## Issues Identified and Fixed

### 1. API Authentication Issues
**Problem**: The subscription routes in the backend require API key authentication via header, but the plugin was sending the API key in the request body.

**Fix**: 
- Updated `make_api_request()` method to send API key in `X-API-Key` header
- Updated `forwardToBackend()` function to forward the API key header
- Removed API key from request body in all methods

### 2. Error Handling and Debugging
**Problem**: Limited error handling and debugging information made it difficult to identify issues.

**Fix**:
- Added comprehensive debugging to `create_subscription()` method
- Added verbose CURL logging to `make_api_request()` method
- Added debugging to AJAX handler in `create_subscription.php`
- Created diagnostic and test scripts

### 3. Database Table Issues
**Problem**: Some required tables might not be properly created during installation.

**Fix**:
- Added table existence checks in `update_subscription_status()` method
- Created diagnostic script to check table status

## Files Modified

### 1. `classes/installation_manager.php`
- Fixed API key handling in `make_api_request()` method
- Removed API key from request body in all methods
- Added comprehensive debugging
- Improved error handling in `create_subscription()` method

### 2. `api_proxy.php`
- Updated `forwardToBackend()` to forward API key header
- Improved header handling

### 3. `ajax/create_subscription.php`
- Added debugging information
- Improved error handling and logging

### 4. New Files Created

#### `diagnostic.php`
- Comprehensive diagnostic tool to check:
  - Installation registration status
  - Database table existence
  - API connectivity
  - Available plans
  - Current subscription status
  - Configuration details

#### `test_subscription.php`
- Test script to verify subscription flow
- Tests registration, API connectivity, plans, and free plan activation

## How to Use the Fixes

### 1. Run Diagnostic
Visit: `/report/adeptus_insights/diagnostic.php`

This will show you:
- ✅ Installation status
- ✅ Database table status
- ✅ API connectivity
- ✅ Available plans
- ✅ Current subscription
- ⚠️ Recommendations for issues

### 2. Test Subscription Flow
Visit: `/report/adeptus_insights/test_subscription.php`

This will test:
- Registration status
- API connectivity
- Available plans
- Free plan activation
- Current subscription

### 3. Check Debug Information
Enable debugging in Moodle and check the debug log for detailed information about:
- API requests and responses
- CURL verbose logs
- Error messages and stack traces

## Common Issues and Solutions

### Issue: "API key is required" error
**Solution**: Ensure the installation is properly registered and the API key is stored in the database.

### Issue: "Invalid API key" error
**Solution**: Re-register the installation to get a new API key.

### Issue: Database tables missing
**Solution**: Reinstall the plugin to create all required tables.

### Issue: API connectivity failed
**Solution**: Check the API URL configuration and ensure the backend is accessible.

## Testing the Fixes

1. **First, run the diagnostic**:
   ```
   https://your-moodle-site.com/report/adeptus_insights/diagnostic.php
   ```

2. **If issues are found, run the test script**:
   ```
   https://your-moodle-site.com/report/adeptus_insights/test_subscription.php
   ```

3. **Check the subscription page**:
   ```
   https://your-moodle-site.com/report/adeptus_insights/subscription.php
   ```

4. **Enable debugging** and check the logs for detailed information.

## Backend API Endpoints

The plugin communicates with these backend endpoints:

- `POST /api/installation/register` - Register installation
- `GET /api/subscription/config` - Get Stripe configuration
- `POST /api/subscription/create` - Create subscription (requires API key)
- `POST /api/subscription/plans` - Get available plans (requires API key)
- `POST /api/subscription/status` - Check subscription status (requires API key)

All authenticated endpoints now properly send the API key in the `X-API-Key` header.

## Next Steps

1. Test the subscription flow with the diagnostic and test scripts
2. Check the debug logs for any remaining issues
3. If problems persist, check the backend API logs
4. Ensure all database tables are properly created
5. Verify the API key is correctly stored and transmitted

The fixes should resolve the subscription onboarding issues and provide better debugging information for future troubleshooting. 