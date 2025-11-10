# Wizard Backend Integration

## Overview

The Adeptus Insights Report Wizard has been updated to use the backend API for parameter processing instead of local PHP functions. This provides enhanced functionality, better error handling, and centralized parameter management.

## Changes Made

### 1. Removed Local Seeder Dependencies

The plugin no longer uses local seeders:
- `adeptus_reports_seed.php` - Deleted
- `cli_seed.php` - Deleted
- `report_adeptus_insights_seed_reports()` function - Removed from install.php

Reports are now seeded exclusively from the backend API. The plugin relies on the backend for report definitions and parameter processing.

### 2. Updated Wizard JavaScript

The wizard now:
- Loads configuration from `config.php`
- Calls backend API endpoints for parameter enhancement
- Provides fallback to local processing if backend fails
- Includes better error handling and debugging

### 3. Enhanced AJAX Endpoint

`get_report_parameters.php` now:
- Attempts to use backend API first
- Falls back to local processing if backend fails
- Includes configuration options
- Provides debug logging

### 4. New Configuration File

`config.php` provides centralized configuration for:
- Backend API URL
- Feature toggles
- Timeout settings
- Debug options

## Backend API Endpoints

### GET /api/adeptus-reports/parameter-types

Returns comprehensive parameter type mapping for automatic dropdown generation.

**Response:**
```json
{
  "success": true,
  "data": {
    "userid": "user_select",
    "courseid": "course_select",
    "categoryid": "category_select",
    // ... more mappings
  }
}
```

### POST /api/adeptus-reports/process-parameter

Processes individual parameters with automatic type detection.

**Request:**
```json
{
  "paramName": "userid",
  "paramConfig": {
    "type": "user_select",
    "label": "Student",
    "description": "Select the student to analyze"
  }
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "name": "userid",
    "label": "Student",
    "type": "user_select",
    "description": "Select the student to analyze",
    "required": true
  }
}
```

## Configuration Options

### Backend API Configuration

```php
// Backend API URL
$CFG->adeptus_backend_api_url = 'https://stagingwithswift.com/opt/adeptus_ai_backend/public/api';

// Enable/disable backend API usage
$CFG->adeptus_wizard_enable_backend_api = true;

// API timeout in seconds
$CFG->adeptus_wizard_api_timeout = 5;

// Enable local fallback if backend fails
$CFG->adeptus_wizard_fallback_to_local = true;
```

### Parameter Processing Configuration

```php
// Enable enhanced parameter processing
$CFG->adeptus_parameter_enhancement = true;

// Parameter cache TTL in seconds
$CFG->adeptus_parameter_cache_ttl = 300;
```

### Debug Configuration

```php
// Enable debug logging
$CFG->adeptus_debug_mode = false;

// Log API calls for debugging
$CFG->adeptus_log_api_calls = true;
```

## Usage

### 1. Basic Usage

The wizard automatically uses the backend API when enabled. No changes are needed to existing reports.

### 2. Custom Parameter Types

To add custom parameter types:

1. Update the backend `AdeptusReportService::getParameterTypeMapping()` method
2. The wizard will automatically use the new types

### 3. Parameter Enhancement

Parameters are automatically enhanced with:
- Better type detection
- Improved labels and descriptions
- Validation rules
- Default values

## Fallback Mechanism

If the backend API is unavailable:

1. The wizard detects the failure
2. Falls back to local parameter processing
3. Logs the failure for debugging
4. Continues to function normally

## Error Handling

### Backend API Failures

- Network timeouts
- Invalid responses
- Server errors
- Authentication issues

### Local Fallback

- Always available as backup
- Processes parameters using local Moodle database
- Maintains full functionality

## Debugging

### Enable Debug Mode

```php
$CFG->adeptus_debug_mode = true;
```

### Check Browser Console

The wizard logs:
- Configuration loading
- API calls and responses
- Fallback usage
- Error details

### Check Server Logs

Look for:
- Backend API call logs
- Parameter processing details
- Error messages

## Migration Guide

### From Local Functions

1. **Before:** Parameters processed using local `processParameter()` function
2. **After:** Parameters enhanced via backend API with local fallback

### Benefits

- **Centralized Management:** All parameter logic in one place
- **Better Performance:** Reduced local processing
- **Enhanced Features:** More parameter types and validation
- **Easier Maintenance:** Single source of truth for parameter logic

### No Breaking Changes

- Existing reports continue to work
- Parameter definitions remain the same
- UI behavior unchanged
- Enhanced functionality is additive

## Troubleshooting

### Backend API Not Responding

1. Check network connectivity
2. Verify backend URL in configuration
3. Check backend server status
4. Review timeout settings

### Parameters Not Enhanced

1. Enable debug mode
2. Check browser console for errors
3. Verify backend API endpoints
4. Review configuration settings

### Performance Issues

1. Adjust API timeout values
2. Enable parameter caching
3. Review fallback settings
4. Monitor API response times

## Future Enhancements

### Planned Features

- Parameter validation rules
- Dynamic option loading
- Parameter dependencies
- Advanced type mapping
- Performance optimization

### API Extensions

- Bulk parameter processing
- Parameter templates
- Custom validation rules
- Integration with other systems

## Support

For issues or questions:

1. Check debug logs
2. Review configuration
3. Test backend API endpoints
4. Contact development team

## Version History

- **v1.0.0:** Initial backend integration
- **v1.1.0:** Enhanced error handling and fallback
- **v1.2.0:** Configuration management and debugging
- **Future:** Advanced parameter features and optimization
