# Adeptus Insights Authentication System - Administrator Guide

## Overview

This guide provides administrators with comprehensive information about configuring, managing, and troubleshooting the new token-based authentication system for the Adeptus Insights plugin.

## System Architecture

### **Components**
1. **Moodle Plugin**: Handles user authentication and API key management
2. **Backend Service**: Validates tokens and manages installations
3. **Database**: Stores installation records and API keys
4. **Cache System**: Optimizes performance and reduces database load

### **Data Flow**
```
Moodle Plugin → API Key + Site URL + User Email → Backend Validation → Database Check → Response
```

## Installation & Setup

### **Prerequisites**
- Moodle 4.1 or higher
- PHP 7.4 or higher
- HTTPS enabled (required for security)
- Plugin installation permissions

### **Installation Steps**
1. **Upload Plugin Files**
   ```bash
   # Copy plugin to Moodle directory
   cp -r adeptus_insights /path/to/moodle/report/
   ```

2. **Install via Moodle Admin**
   - Go to Site Administration → Plugins → Install plugins
   - Upload the plugin ZIP file
   - Follow the installation wizard

3. **Verify Installation**
   - Check Site Administration → Reports → Adeptus Insights
   - Verify plugin appears in the reports section

### **Initial Configuration**
1. **Access Plugin Settings**
   - Navigate to Site Administration → Plugins → Reports → Adeptus Insights
   - Click on "Settings" tab

2. **Review Generated API Key**
   - API key is automatically generated during installation
   - **DO NOT EDIT** this key manually
   - Key is 64 characters long and cryptographically secure

3. **Verify Site URL**
   - Ensure the displayed site URL matches your Moodle installation
   - Update if necessary (e.g., after site migration)

## Configuration Settings

### **Core Settings**

#### **API Configuration**
- **API Key**: Automatically generated, read-only
- **API URL**: Backend service endpoint
- **Installation ID**: Unique identifier for your site

#### **Contact Information**
- **Notification Email**: Primary contact for support
- **Support URL**: Link to documentation or help
- **Documentation URL**: Plugin documentation location
- **Support Phone**: Optional phone support

#### **Performance Settings**
- **Enable Error Logging**: Log authentication errors locally
- **Cache Duration**: How long to cache authentication results
- **Debug Mode**: Enable detailed logging (development only)

### **Security Settings**

#### **Authentication Requirements**
- **Require HTTPS**: Enforce secure connections
- **User Email Validation**: Verify user emails against admin records
- **Site URL Validation**: Ensure site URL matches registration
- **API Key Validation**: Validate API key format and existence

#### **Access Control**
- **User Capabilities**: Define who can access the plugin
- **Role Restrictions**: Limit access to specific user roles
- **IP Restrictions**: Optional IP address filtering

## API Key Management

### **Key Generation**
- **Automatic**: Keys are generated during plugin installation
- **Format**: 64-character hexadecimal string
- **Security**: Cryptographically secure random generation
- **Storage**: Encrypted storage in database

### **Key Validation**
- **Format Check**: Ensures proper 64-character format
- **Existence Check**: Verifies key exists in backend database
- **Active Status**: Confirms key is not revoked or expired
- **Site Association**: Links key to specific Moodle installation

### **Key Security**
- **Read-Only**: Keys cannot be edited in the interface
- **Encryption**: Keys are encrypted at rest
- **Access Control**: Limited to authorized administrators
- **Audit Logging**: All key access is logged

## Site URL Management

### **URL Validation**
- **Format**: Must be valid HTTP/HTTPS URL
- **Domain**: No subdomain restrictions
- **Protocol**: Both HTTP and HTTPS supported
- **Port**: Standard ports (80, 443) recommended

### **URL Updates**
- **When to Update**: Site migration, domain changes
- **How to Update**: Contact backend service administrator
- **Verification**: Test authentication after updates
- **Rollback**: Previous URLs can be restored if needed

### **URL Security**
- **Registration**: Only registered URLs can access the service
- **Validation**: URLs are validated on every request
- **Logging**: All URL validation attempts are logged
- **Monitoring**: Unusual URL patterns are flagged

## User Management

### **User Authentication**
- **Moodle Login**: Users must be logged into Moodle
- **Capability Check**: Users need appropriate permissions
- **Email Validation**: User email must match admin records
- **Session Management**: Respects Moodle session timeouts

### **User Permissions**
- **View Reports**: Basic access to plugin functionality
- **Generate Reports**: Create and export reports
- **Manage Settings**: Access configuration options
- **View Logs**: Access error and activity logs

### **Role Configuration**
- **Administrators**: Full access to all features
- **Teachers**: Access to relevant reports and data
- **Students**: Limited access based on course enrollment
- **Guests**: No access (authentication required)

## Error Handling & Logging

### **Error Types**

#### **Authentication Errors**
- **Missing Headers**: Required authentication headers not provided
- **Invalid API Key**: API key format or validation failure
- **Site URL Mismatch**: Site URL doesn't match registration
- **User Not Authorized**: User lacks required permissions

#### **System Errors**
- **Backend Connection**: Unable to connect to validation service
- **Database Errors**: Database connection or query failures
- **Validation Errors**: Data validation failures
- **Unknown Errors**: Unexpected system errors

### **Error Logging**
- **Local Logs**: Stored in Moodle data directory
- **Backend Logs**: Centralized logging in backend service
- **Audit Trail**: Complete authentication attempt history
- **Performance Metrics**: Response time and success rate tracking

### **Log Management**
- **Log Rotation**: Automatic log file rotation
- **Storage Limits**: Configurable log retention periods
- **Access Control**: Limited to authorized administrators
- **Export Options**: Log data can be exported for analysis

## Performance Optimization

### **Caching Strategy**
- **Authentication Cache**: 5-minute cache for validation results
- **API Key Cache**: Local storage for frequently used keys
- **Database Cache**: Query result caching
- **Frontend Cache**: Browser-based caching for UI elements

### **Database Optimization**
- **Indexed Queries**: Optimized database queries
- **Connection Pooling**: Efficient database connections
- **Query Caching**: Reduced database load
- **Background Processing**: Asynchronous operations where possible

### **Load Handling**
- **Concurrent Users**: Supports 1000+ simultaneous users
- **Request Queuing**: Intelligent request prioritization
- **Resource Management**: Efficient memory and CPU usage
- **Scalability**: Designed for enterprise-level usage

## Monitoring & Maintenance

### **System Health Checks**
- **Authentication Success Rate**: Monitor validation success
- **Response Times**: Track performance metrics
- **Error Rates**: Monitor error frequency and types
- **Resource Usage**: Monitor memory and CPU consumption

### **Regular Maintenance**
- **Log Review**: Weekly review of error logs
- **Performance Monitoring**: Monthly performance analysis
- **Security Updates**: Regular security patch application
- **Backup Verification**: Ensure data backup integrity

### **Alerting & Notifications**
- **Error Thresholds**: Alert when error rates exceed limits
- **Performance Alerts**: Notify when response times degrade
- **Security Alerts**: Flag suspicious authentication patterns
- **System Notifications**: Maintenance and update notifications

## Troubleshooting

### **Common Issues**

#### **Authentication Failures**
1. **Check API Key**: Verify key is valid and active
2. **Verify Site URL**: Ensure URL matches registration
3. **Check User Permissions**: Verify user has required capabilities
4. **Review Error Logs**: Check for specific error details

#### **Performance Issues**
1. **Check Cache Status**: Verify caching is working properly
2. **Monitor Database**: Check for database performance issues
3. **Review Network**: Ensure backend service is accessible
4. **Check Resources**: Monitor server resource usage

#### **Configuration Problems**
1. **Verify Settings**: Check all configuration values
2. **Test Connections**: Verify backend service connectivity
3. **Check Permissions**: Ensure proper file and directory permissions
4. **Review Logs**: Check for configuration-related errors

### **Debug Mode**
- **Enable Debug**: Set debug mode in plugin settings
- **Detailed Logging**: Enhanced error and activity logging
- **Performance Profiling**: Detailed performance metrics
- **Development Use**: Only enable in development environments

## Security Considerations

### **Data Protection**
- **Encryption**: All sensitive data is encrypted
- **Access Control**: Strict access control and authentication
- **Audit Logging**: Complete audit trail for all operations
- **Data Retention**: Configurable data retention policies

### **Network Security**
- **HTTPS Required**: All communications use HTTPS
- **API Key Security**: Secure transmission and storage
- **Request Validation**: Comprehensive input validation
- **Rate Limiting**: Protection against abuse and attacks

### **Compliance**
- **GDPR Compliance**: Data protection and privacy compliance
- **Security Standards**: Industry-standard security practices
- **Regular Audits**: Periodic security assessments
- **Vulnerability Management**: Regular security updates

## Backup & Recovery

### **Data Backup**
- **Configuration Backup**: Regular backup of plugin settings
- **Log Backup**: Backup of authentication and error logs
- **Database Backup**: Backup of plugin-related database tables
- **File Backup**: Backup of plugin files and customizations

### **Recovery Procedures**
- **Configuration Recovery**: Restore plugin settings from backup
- **Data Recovery**: Restore lost or corrupted data
- **System Recovery**: Complete system restoration procedures
- **Rollback Procedures**: Revert to previous working versions

### **Disaster Recovery**
- **Business Continuity**: Ensure continuous service availability
- **Data Recovery**: Minimize data loss in disaster scenarios
- **Service Restoration**: Rapid service restoration procedures
- **Communication Plans**: User and stakeholder communication

## Support & Resources

### **Documentation**
- **User Guide**: Comprehensive user documentation
- **API Reference**: Technical API documentation
- **Troubleshooting Guide**: Common problems and solutions
- **Video Tutorials**: Step-by-step visual guides

### **Technical Support**
- **Email Support**: Direct technical support contact
- **Support Portal**: Online support ticket system
- **Community Forum**: User community support
- **Emergency Contact**: Critical issue escalation

### **Training Resources**
- **Administrator Training**: Comprehensive admin training
- **User Training**: End-user training materials
- **Best Practices**: Recommended configuration and usage
- **Case Studies**: Real-world implementation examples

## Version Management

### **Update Process**
1. **Backup Current Version**: Complete backup before update
2. **Review Release Notes**: Check for breaking changes
3. **Test in Staging**: Verify functionality in test environment
4. **Deploy to Production**: Apply update to production system
5. **Verify Functionality**: Confirm all features work correctly

### **Rollback Procedures**
1. **Identify Issues**: Determine what's not working
2. **Prepare Rollback**: Ready previous version for deployment
3. **Execute Rollback**: Revert to previous working version
4. **Verify Stability**: Confirm system is stable
5. **Investigate Issues**: Determine cause of problems

### **Compatibility**
- **Moodle Versions**: Tested with supported Moodle versions
- **PHP Versions**: Compatible PHP version requirements
- **Database Systems**: Supported database platforms
- **Browser Support**: Compatible web browsers

---

*This guide is part of the Adeptus Insights plugin documentation. For the latest updates and additional resources, visit the plugin's documentation section.*
