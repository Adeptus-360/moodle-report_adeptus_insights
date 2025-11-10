# Adeptus AI Plugin Authentication System

## Overview

The Adeptus AI plugin now uses a **plugin-wide authentication system** that manages user authentication, subscription status, and usage limits across ALL plugin pages and features. This system provides consistent authentication checking and user experience throughout the entire plugin, not just in the AI assistant.

## Key Features

### 1. Plugin-Wide Authentication
- **Global Authentication Manager**: PHP class that provides authentication checking for all plugin pages
- **Global JavaScript Module**: JavaScript module that handles authentication across all plugin pages
- **API Key Management**: Uses API keys instead of Bearer tokens for backend communication
- **Automatic Validation**: Checks authentication status every 5 minutes
- **Session Storage**: Secure storage of authentication data in browser sessionStorage

### 2. Automatic User Data Fetching
- **User Profile**: Automatically fetches user details, subscription, and usage data from the backend
- **Subscription Status**: Tracks active subscription plans and limits
- **Usage Monitoring**: Monitors monthly usage for reports and AI credits

### 3. Usage Limit Enforcement
- **Pre-request Validation**: Checks usage limits before making API calls
- **Real-time Feedback**: Shows warnings when limits are reached
- **Action-specific Limits**: Different limits for chat messages vs. report generation

### 4. Global Login Modal
- **Fallback Authentication**: When tokens are lost, shows a global login modal
- **Admin Email Auto-fill**: Automatically fills admin email from Moodle configuration
- **Secure Login**: Handles authentication through the Laravel backend

## Architecture

### Files Structure
```
classes/
├── auth_manager.php        # PHP authentication manager for all plugin pages
└── installation_manager.php # Existing installation manager (enhanced)

amd/src/
├── global_auth.js          # Global JavaScript authentication module
├── plugin_auth_init.js     # Plugin-wide authentication initializer
├── auth_helper.js          # Legacy authentication helper (for AI assistant)
├── assistant.js            # Updated AI assistant (uses global auth)
└── test_auth.js           # Test file for authentication functionality

templates/
└── global_auth_header.mustache # Global authentication header template
```

### Dependencies
- **jQuery**: For DOM manipulation and AJAX calls
- **Moodle Core**: For notifications and AJAX utilities
- **SweetAlert2**: For user interface modals

## Usage

### Plugin-Wide Implementation

#### **1. PHP Pages (All Plugin Pages)**
```php
<?php
require_once('../../config.php');
require_once($CFG->dirroot . '/report/adeptus_insights/classes/auth_manager.php');

// Use the new authentication manager
$auth_manager = new \report_adeptus_insights\auth_manager();
$auth_manager->check_auth(true); // This will redirect if not authenticated

// Your page content here...
```

#### **2. JavaScript Pages (All Plugin Pages)**
```javascript
// Include the plugin-wide authentication initializer
require(['report_adeptus_insights/plugin_auth_init'], function(PluginAuthInit) {
    PluginAuthInit.init({
        requireAuth: true,           // Require authentication
        showLoginModal: true,        // Show login modal if not authenticated
        onAuthenticated: function(authStatus) {
            // User is authenticated, proceed with page functionality
            console.log('Authenticated:', authStatus);
        },
        onNotAuthenticated: function(authStatus) {
            // User is not authenticated
            console.log('Not authenticated:', authStatus);
        }
    });
});
```

#### **3. Template Pages (All Plugin Pages)**
```mustache
<!-- Include the global authentication header -->
{{> report_adeptus_insights/global_auth_header }}

<!-- Your page content here -->
```

### Legacy Initialization (AI Assistant Only)
```javascript
// In your main module
define(['report_adeptus_insights/auth_helper'], function(AuthHelper) {
    AuthHelper.init().then((isAuthenticated) => {
        if (isAuthenticated) {
            // User is authenticated, proceed with initialization
            console.log('User:', AuthHelper.getCurrentUser());
            console.log('Subscription:', AuthHelper.getCurrentSubscription());
        } else {
            // User needs to login
            AuthHelper.showGlobalLoginModal();
        }
    });
});
```

### Checking Authentication Status
```javascript
if (AuthHelper.isUserAuthenticated()) {
    // User is logged in
    const user = AuthHelper.getCurrentUser();
    const token = AuthHelper.getToken();
} else {
    // User needs to login
    AuthHelper.showGlobalLoginModal();
}
```

### Usage Limit Checking
```javascript
// Check if user can send a chat message
const chatLimit = AuthHelper.checkUsageLimits('ai_chat');
if (chatLimit.allowed) {
    // Proceed with chat
    console.log('Remaining credits:', chatLimit.remaining);
} else {
    // Show limit reached message
    console.log('Limit reached:', chatLimit.reason);
}

// Check if user can generate a report
const reportLimit = AuthHelper.checkUsageLimits('generate_report');
if (reportLimit.allowed) {
    // Proceed with report generation
} else {
    // Show limit reached message
}
```

### Making Authenticated Requests
```javascript
// Use the helper for all AJAX requests
AuthHelper.authenticatedRequest({
    url: 'https://ai-backend.stagingwithswift.com/api/chat/message',
    method: 'POST',
    data: { message: 'Hello AI' },
    success: function(response) {
        console.log('Success:', response);
    },
    error: function(xhr, status, error) {
        console.error('Error:', error);
    }
});
```

### Authentication Flow
```javascript
// 1. Check if user is authenticated
if (AuthHelper.isUserAuthenticated()) {
    // User has valid API key
    const apiKey = AuthHelper.getApiKey();
    const installationId = AuthHelper.getInstallationId();
} else {
    // Show login modal
    AuthHelper.showGlobalLoginModal();
}

// 2. Listen for authentication success
$(document).on('adeptus:auth:success', function(event, data) {
    console.log('Authentication successful:', data);
    // Proceed with plugin initialization
});
```

## Configuration

### Backend API Endpoints
The authentication helper expects these backend endpoints:

- **POST** `/api/installation/verify` - Installation verification and API key generation
- **GET** `/api/subscription/status` - Subscription details from backend
- **GET** `/api/subscription/plans` - Available subscription plans and limits
- **GET** `/ajax/get_usage_data.php` - Local usage data from Moodle database

### Session Storage Keys
- `ai_api_key` - API key for backend authentication
- `ai_user` - User information
- `ai_subscription` - Subscription details
- `ai_usage` - Usage statistics
- `ai_last_check` - Last authentication check timestamp
- `ai_installation_id` - Installation ID from backend

## Error Handling

### Authentication Failures
- **401 Unauthorized**: Automatically clears stored data and shows login modal
- **Token Expired**: Refreshes user data or prompts for re-authentication
- **Network Errors**: Graceful fallback with user notifications

### Usage Limit Exceeded
- **Pre-request Validation**: Prevents API calls when limits are reached
- **User Notification**: Clear messages about what limits were exceeded
- **Action Blocking**: Disables functionality until limits reset

## Security Features

### Token Management
- **Automatic Validation**: Tokens are validated every 5 minutes
- **Secure Storage**: Data stored in sessionStorage (cleared on logout)
- **Automatic Cleanup**: Invalid tokens are automatically removed

### Session Handling
- **Logout Functionality**: Secure logout that clears all stored data
- **Event Triggers**: Custom events for authentication state changes
- **Page Reload Protection**: Prevents unauthorized access to protected features

## Integration with Moodle

### Moodle Configuration
- **Admin Email**: Automatically detected from Moodle site configuration
- **Session Management**: Integrates with Moodle's session system
- **User Context**: Maintains user context across plugin pages

### Synchronization with Diagnostic System
The authentication helper is fully synchronized with the existing diagnostic.php approach:

#### **Data Sources**
- **Backend API**: Uses the same endpoints as diagnostic.php (`/api/subscription/status`, `/api/subscription/plans`)
- **Local Database**: Reads from the same tables (`adeptus_subscription_status`, `adeptus_usage_tracking`)
- **Installation Manager**: Leverages the same PHP class for backend communication

#### **Authentication Method**
- **API Key System**: Uses `X-API-Key` header instead of Bearer tokens
- **Installation Verification**: Authenticates through `/api/installation/verify` endpoint
- **Local Storage**: Stores API key and installation ID in sessionStorage

#### **Usage Tracking**
- **Real-time Data**: Fetches current month usage from local database
- **Subscription Limits**: Gets plan limits from backend API
- **Consistent Metrics**: Same data structure as diagnostic.php

### Plugin Integration
- **Event System**: Uses Moodle's notification system for user feedback
- **AJAX Utilities**: Leverages Moodle's AJAX handling capabilities
- **Template System**: Integrates with Moodle's template rendering

## Testing

### Test File
Use `test_auth.js` to verify authentication functionality:

```javascript
// Load the test module
require(['report_adeptus_insights/test_auth'], function(TestAuth) {
    TestAuth.init();
});
```

### Test Coverage
- Module loading and initialization
- Authentication status checking
- User data retrieval
- Usage limit validation
- Token management
- Error handling

## Migration from Old System

### Changes Made
1. **Removed**: AI assistant login modal
2. **Added**: Global authentication helper
3. **Updated**: All AJAX calls to use authentication helper
4. **Enhanced**: Usage limit checking and enforcement
5. **Improved**: Error handling and user feedback

### Backward Compatibility
- Existing tokens are automatically migrated
- User data is preserved during the transition
- No changes required to backend API endpoints

## Troubleshooting

### Common Issues

#### Authentication Modal Not Showing
- Check browser console for JavaScript errors
- Verify AuthHelper module is loaded correctly
- Ensure SweetAlert2 is available

#### Token Validation Failing
- Check backend API endpoint availability
- Verify CORS configuration
- Check network connectivity

#### Usage Limits Not Working
- Verify subscription data is being fetched
- Check backend usage tracking implementation
- Ensure proper API response format

### Debug Mode
Enable debug logging by checking browser console:
```javascript
// All authentication operations are logged with [AuthHelper] prefix
console.log('[AuthHelper] Debug information');
```

## Upgrades & Improvements

### **Major Upgrades Implemented**

#### **1. Plugin-Wide Authentication Coverage**
- **Before**: Authentication only in AI assistant
- **After**: Authentication on ALL plugin pages (diagnostic, subscription, reports, etc.)
- **Benefit**: Consistent security across entire plugin

#### **2. API Key System Integration**
- **Before**: Bearer token system (not synchronized with diagnostic.php)
- **After**: API key system using `X-API-Key` headers (fully synchronized)
- **Benefit**: Uses existing backend infrastructure and database

#### **3. Real-Time Usage Tracking**
- **Before**: Static usage data from backend only
- **After**: Live usage data from local database + backend limits
- **Benefit**: Accurate, real-time usage monitoring

#### **4. Unified Authentication Manager**
- **Before**: Scattered authentication checks across different files
- **After**: Single `auth_manager.php` class for all PHP pages
- **Benefit**: Centralized, maintainable authentication logic

#### **5. Global JavaScript Authentication**
- **Before**: Page-specific authentication handling
- **After**: Global authentication module for all JavaScript pages
- **Benefit**: Consistent user experience and behavior

### **Technical Improvements**

#### **Performance Enhancements**
- **Caching**: Authentication status cached for 5 minutes
- **Efficient API Calls**: Single endpoint for auth status
- **Background Updates**: Periodic authentication validation

#### **Security Improvements**
- **Capability Checking**: Moodle capability validation
- **Session Management**: Secure session storage
- **Automatic Cleanup**: Invalid credentials automatically removed

#### **User Experience Improvements**
- **Global Login Modal**: Consistent login interface across all pages
- **Real-Time Status**: Live authentication and usage status
- **Seamless Navigation**: No authentication interruptions

### **Future Enhancements**

#### **Planned Features**
- **Token Refresh**: Automatic API key renewal before expiration
- **Offline Support**: Cached authentication for offline usage
- **Multi-factor Authentication**: Enhanced security options
- **Usage Analytics**: Detailed usage tracking and reporting
- **Subscription Management**: In-plugin subscription upgrades/downgrades

#### **API Extensions**
- **Webhook Support**: Real-time usage updates
- **Batch Operations**: Efficient bulk data operations
- **Advanced Metrics**: Detailed performance analytics

### API Extensions
- **Webhook Support**: Real-time usage updates
- **Batch Operations**: Efficient bulk data operations
- **Advanced Metrics**: Detailed performance analytics
