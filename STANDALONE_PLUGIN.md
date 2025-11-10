# Adeptus Insights - Standalone Plugin Architecture

## Overview

The Adeptus Insights plugin has been redesigned as a completely standalone plugin that connects to the backend only via the API proxy. All subscription data, payment processing, and business logic are managed on the backend, while the plugin focuses on user interface and local caching.

## Architecture Principles

### 1. Plugin Independence
- **No local business logic**: All subscription logic on backend
- **No payment processing**: All payments handled by backend
- **No sensitive data**: No API keys or payment data stored locally
- **API-first design**: Plugin only communicates via API proxy

### 2. Backend Management
- **Centralized data**: All plans, subscriptions, and billing on backend
- **Secure processing**: Payment processing isolated on backend
- **Scalable architecture**: Backend handles complex business logic
- **Multi-tenant support**: Backend manages multiple installations

### 3. Local Caching
- **Performance optimization**: Local cache for fast access
- **Offline capability**: Basic functionality with cached data
- **Webhook updates**: Real-time updates via webhooks
- **Minimal storage**: Only essential data stored locally

## Complete Installation Flow

### Step 1: Plugin Installation
```
Admin installs plugin → Creates database tables → Adds menu entry → Redirects to settings
```

**What happens:**
1. Plugin creates local tables for caching
2. Plugin adds menu entry to Moodle
3. Plugin redirects to settings page

**Local tables created:**
- `adeptus_install_settings` - API connection details
- `adeptus_subscription_status` - Subscription cache
- `adeptus_reports` - Report definitions
- `adeptus_stripe_webhooks` - Webhook tracking
- `adeptus_usage_tracking` - Usage tracking

### Step 2: First Access & Registration
```
User accesses plugin → Checks registration → Shows registration form → Backend registration
```

**What happens:**
1. Plugin checks if installation is registered
2. If not registered, shows registration form
3. User provides admin details
4. Plugin registers with backend API
5. Backend generates API key and installation record

### Step 3: Auto-Free Subscription
```
Registration successful → Gets plans from backend → Finds free plan → Activates subscription
```

**What happens:**
1. Backend registration successful
2. Plugin automatically gets available plans
3. Plugin finds free plan from backend
4. Plugin activates free subscription via API
5. Plugin caches subscription details locally

### Step 4: Main Dashboard
```
User sees dashboard → Can manage subscription → Can upgrade plans → Can use reports
```

**What happens:**
1. User sees main dashboard with subscription status
2. User can view and run reports
3. User can manage subscription
4. User can upgrade/downgrade plans

## API Communication

### Registration Endpoints
- `POST /api/installation/register` - Register new installation
- `POST /api/installation/verify` - Verify installation status

### Subscription Endpoints
- `GET /api/subscription/config` - Get payment configuration
- `POST /api/subscription/plans` - Get available plans
- `POST /api/subscription/activate-free` - Activate free plan
- `POST /api/subscription/create` - Create paid subscription
- `POST /api/subscription/status` - Check subscription status

### Report Endpoints
- `POST /api/installation/reports` - Get compatible reports
- `POST /api/reports/sync` - Sync reports from backend

## Security Model

### 1. API Key Authentication
- API keys generated on backend
- Stored securely in local database
- Sent in headers for all authenticated requests
- No sensitive data in request body

### 2. Payment Security
- No payment data stored locally
- All payment processing on backend
- Payment configuration managed on backend
- Plugin only handles UI for payments

### 3. Data Privacy
- Minimal sensitive data stored locally
- Most data managed on backend
- Local cache for performance only
- Webhook updates for real-time sync

## Local Data Storage

### Essential Data (Plugin Side)
1. **Installation Settings**
   - API key and connection details
   - Required for plugin operation

2. **Subscription Cache**
   - Current subscription details
   - Updated via webhooks and API calls

3. **Report Definitions**
   - Report templates and queries
   - Synced from backend

4. **Usage Tracking**
   - Local usage statistics
   - Helps with quota management

5. **Webhook Events**
   - Tracks webhook events
   - Prevents duplicate processing

### Backend Data (API Side)
1. **Installation Records**
   - Registered installations
   - API key management

2. **Subscription Plans**
   - Available plans and pricing
   - Managed centrally

3. **User Subscriptions**
   - Active subscriptions
   - Billing information

4. **Payment Configuration**
   - Payment provider settings
   - Managed securely

## Error Handling

### 1. API Connectivity Issues
- Graceful degradation
- Retry mechanisms
- Fallback to cached data
- Clear error messages

### 2. Registration Failures
- Allow retry of registration
- Clear error messages
- Support contact information
- Debug information for troubleshooting

### 3. Subscription Issues
- Clear error messages
- Support contact information
- Debug information
- Fallback options

## Testing & Diagnostics

### 1. Diagnostic Tools
```bash
# Run comprehensive diagnostic
https://your-site.com/report/adeptus_insights/diagnostic.php

# Test subscription flow
https://your-site.com/report/adeptus_insights/test_subscription.php
```

### 2. Debug Information
- Enable Moodle debugging
- Check plugin logs
- Monitor API requests/responses
- Verify webhook events

### 3. Common Issues
- **"Installation not registered"** - Complete registration
- **"No plans available"** - Check backend configuration
- **"Payment configuration failed"** - Check backend setup
- **"Database tables missing"** - Reinstall plugin

## Benefits of This Architecture

### 1. Security
- No sensitive data stored locally
- Payment processing isolated
- API key management secure
- Minimal attack surface

### 2. Scalability
- Backend handles complex logic
- Easy to add new features
- Centralized data management
- Multi-tenant support

### 3. Maintainability
- Plugin updates not needed for plan changes
- Backend can manage business logic
- Clear separation of concerns
- Easy to debug and troubleshoot

### 4. User Experience
- Smooth installation process
- Automatic free subscription
- Real-time updates
- Offline capability

## Future Enhancements

### 1. Real-time Updates
- WebSocket connections
- Live subscription updates
- Real-time usage tracking

### 2. Advanced Caching
- Intelligent cache invalidation
- Offline mode improvements
- Performance optimizations

### 3. Multi-tenant Features
- Organization-level management
- Bulk subscription management
- Advanced analytics

### 4. Custom Branding
- White-label options
- Custom themes
- Branded interfaces

The standalone plugin architecture ensures security, scalability, and maintainability while providing a smooth user experience. All business logic is managed on the backend, while the plugin focuses on user interface and local performance optimization. 