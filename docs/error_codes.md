# Adeptus Insights Error Codes - Complete Reference

## Overview

This document provides a comprehensive reference for all error codes in the Adeptus Insights authentication system. Each error code includes detailed information about the cause, impact, and resolution steps.

## Error Code Categories

### 🔴 **Critical Errors** (Severity: Error)
These errors prevent the plugin from functioning and require immediate attention.

### ⚠️ **Warning Errors** (Severity: Warning)
These errors limit functionality but allow basic operations to continue.

### ℹ️ **Information Errors** (Severity: Info)
These errors provide information about system status or configuration.

## Complete Error Code Reference

### **MISSING_HEADERS**
- **Severity**: Error
- **HTTP Status**: 400 Bad Request
- **Description**: Required authentication headers are missing from the request
- **User Message**: "The plugin is missing required authentication information. This usually indicates a configuration issue."
- **Technical Details**: One or more of the required headers (X-API-Key, X-Site-URL, X-User-Email) are not present in the request
- **Recovery Action**: refresh_page
- **Common Causes**:
  - Plugin configuration corruption
  - JavaScript errors preventing header transmission
  - Network issues during request
  - Plugin files not properly loaded
- **Resolution Steps**:
  1. Refresh the page to retry the request
  2. Check browser console for JavaScript errors
  3. Verify plugin files are properly installed
  4. Contact administrator if issue persists
- **Prevention**:
  - Ensure plugin is properly installed
  - Keep browser and Moodle updated
  - Monitor for JavaScript errors

### **INVALID_API_KEY_FORMAT**
- **Severity**: Error
- **HTTP Status**: 400 Bad Request
- **Description**: The API key format is invalid or corrupted
- **User Message**: "The plugin's API key appears to be corrupted or in an invalid format."
- **Technical Details**: The API key does not match the expected 64-character hexadecimal format
- **Recovery Action**: contact_admin
- **Common Causes**:
  - Database corruption
  - Plugin update issues
  - Manual key modification
  - System configuration errors
- **Resolution Steps**:
  1. Contact your plugin administrator immediately
  2. Do not attempt to modify the key manually
  3. Administrator will regenerate the API key
  4. Verify functionality after key regeneration
- **Prevention**:
  - Never manually edit API keys
  - Keep plugin updated
  - Regular database maintenance
  - Backup configuration before updates

### **INVALID_API_KEY**
- **Severity**: Error
- **HTTP Status**: 401 Unauthorized
- **Description**: The API key is invalid or has been revoked
- **User Message**: "Your plugin's API key is no longer valid. This may happen if the key was revoked or expired."
- **Technical Details**: The API key exists but is not recognized by the backend service
- **Recovery Action**: contact_admin
- **Common Causes**:
  - API key revocation by administrator
  - Key expiration
  - Backend service configuration changes
  - Security policy enforcement
- **Resolution Steps**:
  1. Contact your plugin administrator
  2. Administrator will verify key status
  3. New key will be generated if necessary
  4. Plugin will be reconfigured with new key
- **Prevention**:
  - Regular key validation
  - Monitor backend service status
  - Keep contact information updated
  - Regular security audits

### **SITE_URL_MISMATCH**
- **Severity**: Warning
- **HTTP Status**: 403 Forbidden
- **Description**: The site URL does not match the registered installation
- **User Message**: "The current site URL doesn't match what's registered with the plugin service. This often happens after site migrations or URL changes."
- **Technical Details**: The site URL in the request differs from the URL stored in the backend database
- **Recovery Action**: contact_admin
- **Common Causes**:
  - Site migration to new domain
  - URL configuration changes
  - Load balancer configuration
  - SSL certificate changes
- **Resolution Steps**:
  1. Contact your plugin administrator
  2. Administrator will update site URL registration
  3. Verify new URL configuration
  4. Test authentication after update
- **Prevention**:
  - Plan URL changes in advance
  - Update registrations before changes
  - Test configuration after changes
  - Keep documentation updated

### **UNAUTHORIZED_USER**
- **Severity**: Warning
- **HTTP Status**: 403 Forbidden
- **Description**: Your user account is not authorized to access this plugin
- **User Message**: "Your user account doesn't have permission to access the Adeptus Insights plugin. Please contact your administrator."
- **Technical Details**: The user's email address does not match any registered admin email for this installation
- **Recovery Action**: contact_admin
- **Common Causes**:
  - User role changes
  - Permission updates
  - Admin email changes
  - User account modifications
- **Resolution Steps**:
  1. Contact your Moodle administrator
  2. Verify your user role and permissions
  3. Check if admin email needs updating
  4. Request appropriate access permissions
- **Prevention**:
  - Regular permission reviews
  - Keep admin contact information updated
  - Document user role requirements
  - Regular access audits

### **SUBSCRIPTION_INACTIVE**
- **Severity**: Warning
- **HTTP Status**: 402 Payment Required
- **Description**: Your subscription is inactive or has expired
- **User Message**: "Your plugin subscription is currently inactive. Please contact your administrator to renew or activate your subscription."
- **Technical Details**: The subscription associated with this installation is not active
- **Recovery Action**: contact_admin
- **Common Causes**:
  - Subscription expiration
  - Payment issues
  - Account suspension
  - Service configuration changes
- **Resolution Steps**:
  1. Contact your plugin administrator
  2. Administrator will check subscription status
  3. Renew or activate subscription as needed
  4. Verify functionality after activation
- **Prevention**:
  - Monitor subscription expiration dates
  - Keep payment information updated
  - Regular subscription status checks
  - Automated renewal where possible

### **INSUFFICIENT_TOKENS**
- **Severity**: Warning
- **HTTP Status**: 402 Payment Required
- **Description**: You have insufficient tokens for this operation
- **User Message**: "You don't have enough tokens to perform this operation. Please contact your administrator to purchase more tokens."
- **Technical Details**: The operation requires more tokens than are available in the account
- **Recovery Action**: contact_admin
- **Common Causes**:
  - Token depletion
  - High usage rates
  - Token allocation changes
  - Service plan limitations
- **Resolution Steps**:
  1. Contact your plugin administrator
  2. Administrator will check token balance
  3. Purchase additional tokens if needed
  4. Verify token allocation after purchase
- **Prevention**:
  - Monitor token usage regularly
  - Set usage alerts and limits
  - Plan token purchases in advance
  - Optimize operations to reduce token usage

### **BACKEND_CONNECTION_FAILED**
- **Severity**: Warning
- **HTTP Status**: 503 Service Unavailable
- **Description**: Unable to connect to the plugin service
- **User Message**: "The plugin service is currently unavailable. This may be a temporary issue. Please try again later."
- **Technical Details**: The plugin cannot establish a connection to the backend validation service
- **Recovery Action**: retry_later
- **Common Causes**:
  - Network connectivity issues
  - Backend service maintenance
  - Firewall or proxy configuration
  - DNS resolution problems
- **Resolution Steps**:
  1. Wait a few minutes and try again
  2. Check your internet connection
  3. Verify network configuration
  4. Contact support if issue persists
- **Prevention**:
  - Monitor network connectivity
  - Configure appropriate timeouts
  - Use reliable network connections
  - Regular service status checks

### **VALIDATION_ERROR**
- **Severity**: Error
- **HTTP Status**: 422 Unprocessable Entity
- **Description**: An error occurred during authentication validation
- **User Message**: "There was an error validating your authentication. This may be a temporary system issue."
- **Technical Details**: The validation process encountered an unexpected error
- **Recovery Action**: refresh_page
- **Common Causes**:
  - Backend service errors
  - Data validation failures
  - System configuration issues
  - Temporary service problems
- **Resolution Steps**:
  1. Refresh the page to retry
  2. Wait a few minutes before retrying
  3. Check for system maintenance notifications
  4. Contact support if issue persists
- **Prevention**:
  - Regular system health checks
  - Monitor validation success rates
  - Keep systems updated
  - Regular maintenance schedules

### **DATABASE_ERROR**
- **Severity**: Error
- **HTTP Status**: 500 Internal Server Error
- **Description**: A database error occurred during validation
- **User Message**: "A system error occurred while validating your access. Please try again later."
- **Technical Details**: The backend service encountered a database error during validation
- **Recovery Action**: retry_later
- **Common Causes**:
  - Database connection issues
  - Query execution errors
  - Database maintenance
  - System resource limitations
- **Resolution Steps**:
  1. Wait a few minutes before retrying
  2. Check for system maintenance notifications
  3. Verify backend service status
  4. Contact support if issue persists
- **Prevention**:
  - Regular database maintenance
  - Monitor database performance
  - Regular backup procedures
  - Resource monitoring and alerts

### **UNKNOWN_ERROR**
- **Severity**: Error
- **HTTP Status**: 500 Internal Server Error
- **Description**: An unexpected error occurred
- **User Message**: "An unexpected error occurred. Please contact your administrator for assistance."
- **Technical Details**: An unclassified error occurred that doesn't match known error patterns
- **Recovery Action**: contact_admin
- **Common Causes**:
  - Unhandled exceptions
  - System configuration issues
  - Unexpected service states
  - Integration problems
- **Resolution Steps**:
  1. Contact your plugin administrator
  2. Provide error details and context
  3. Administrator will investigate the issue
  4. Follow administrator guidance for resolution
- **Prevention**:
  - Regular system monitoring
  - Comprehensive error handling
  - Regular system updates
  - Proactive issue detection

## Error Recovery Matrix

| Error Code | User Action | Admin Action | Expected Resolution Time |
|------------|-------------|--------------|-------------------------|
| MISSING_HEADERS | Refresh page | Check configuration | Immediate |
| INVALID_API_KEY_FORMAT | Contact admin | Regenerate key | 1-2 hours |
| INVALID_API_KEY | Contact admin | Verify/regenerate key | 2-4 hours |
| SITE_URL_MISMATCH | Contact admin | Update registration | 4-8 hours |
| UNAUTHORIZED_USER | Contact admin | Update permissions | 1-2 hours |
| SUBSCRIPTION_INACTIVE | Contact admin | Renew subscription | 4-24 hours |
| INSUFFICIENT_TOKENS | Contact admin | Purchase tokens | 2-8 hours |
| BACKEND_CONNECTION_FAILED | Wait and retry | Check service status | 15-60 minutes |
| VALIDATION_ERROR | Refresh page | Monitor system | 15-30 minutes |
| DATABASE_ERROR | Wait and retry | Check database | 30-120 minutes |
| UNKNOWN_ERROR | Contact admin | Investigate issue | 2-8 hours |

## Error Logging & Monitoring

### **Log Levels**
- **Error**: Critical issues requiring immediate attention
- **Warning**: Issues that limit functionality but allow operation
- **Info**: Informational messages about system status

### **Log Retention**
- **Error Logs**: 90 days
- **Warning Logs**: 30 days
- **Info Logs**: 7 days
- **Debug Logs**: 1 day (development only)

### **Monitoring Alerts**
- **Error Rate Threshold**: >5% errors in 5 minutes
- **Response Time Alert**: >500ms average response time
- **Connection Failure Alert**: >3 consecutive failures
- **Authentication Failure Alert**: >10% failure rate

## Best Practices for Error Handling

### **For Users**
1. **Read Error Messages Carefully**: They contain specific guidance
2. **Follow Recovery Actions**: Use the provided recovery steps
3. **Contact Administrator**: When recovery actions don't work
4. **Document Issues**: Note error codes and context for support

### **For Administrators**
1. **Monitor Error Logs**: Regular review of error patterns
2. **Set Up Alerts**: Configure monitoring for critical errors
3. **Maintain Documentation**: Keep error resolution procedures updated
4. **Regular Reviews**: Periodic analysis of error trends

### **For Developers**
1. **Comprehensive Logging**: Log all relevant error details
2. **User-Friendly Messages**: Clear, actionable error messages
3. **Recovery Actions**: Provide specific steps for resolution
4. **Error Classification**: Proper categorization of error types

## Support Escalation

### **Level 1: User Self-Service**
- Error message guidance
- Recovery action instructions
- Basic troubleshooting steps

### **Level 2: Administrator Support**
- Configuration issues
- Permission problems
- Basic system problems

### **Level 3: Technical Support**
- Complex technical issues
- System integration problems
- Performance optimization

### **Level 4: Emergency Support**
- Critical system failures
- Security incidents
- Service outages

---

*This error code reference is part of the Adeptus Insights plugin documentation. For the latest updates and additional support resources, visit the plugin's documentation section.*
