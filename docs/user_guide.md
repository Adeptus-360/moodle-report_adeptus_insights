# Adeptus Insights Authentication System - User Guide

## Overview

The Adeptus Insights plugin now uses a modern, secure token-based authentication system that replaces the previous global authentication method. This system provides enhanced security, better performance, and improved user experience.

## What's New

### ✅ **Enhanced Security**
- Token-based authentication instead of global credentials
- Site URL validation to prevent unauthorized access
- User email verification against registered administrators
- Comprehensive error handling and logging

### ✅ **Improved Performance**
- Caching system for faster authentication
- Optimized validation processes
- Reduced database queries through intelligent caching

### ✅ **Better User Experience**
- Professional error messages with recovery suggestions
- Read-only mode for authentication issues
- Clear guidance on resolving problems
- Integrated admin contact information

## How It Works

### 1. **Authentication Flow**
```
User Login → Plugin Check → API Key Validation → Site URL Verification → User Email Check → Access Granted
```

### 2. **Token System**
- Each Moodle installation has a unique API key
- API keys are automatically generated during plugin installation
- Keys are stored securely and cannot be manually edited
- Validation occurs on every plugin access

### 3. **Security Measures**
- API keys are 64 characters long and cryptographically secure
- Site URLs are validated against registered installations
- User emails must match registered admin emails
- All authentication attempts are logged for security

## User Experience

### **Normal Operation**
When everything is working correctly, you'll see the plugin interface normally with full functionality.

### **Authentication Issues**
If there are authentication problems, the plugin will enter "Read-Only Mode" with clear error messages and recovery options.

## Error Messages & Solutions

### **Common Error Types**

#### 🔴 **Invalid API Key**
- **What it means**: Your plugin's API key is no longer valid
- **Possible causes**: Key corruption, system updates, or configuration changes
- **Solution**: Contact your plugin administrator

#### ⚠️ **Site URL Mismatch**
- **What it means**: Your site URL doesn't match what's registered
- **Possible causes**: Site migration, URL changes, or configuration updates
- **Solution**: Contact your plugin administrator to update registration

#### ⚠️ **User Not Authorized**
- **What it means**: Your user account doesn't have permission
- **Possible causes**: Role changes or permission updates
- **Solution**: Contact your administrator to grant access

#### ⚠️ **Subscription Inactive**
- **What it means**: Your plugin subscription has expired
- **Possible causes**: Payment issues or subscription expiration
- **Solution**: Contact your administrator to renew subscription

#### ⚠️ **Insufficient Tokens**
- **What it means**: You don't have enough tokens for operations
- **Possible causes**: Token depletion or usage limits
- **Solution**: Contact your administrator to purchase more tokens

### **Recovery Actions**

Each error message includes specific recovery actions:

1. **🔄 Refresh Page**: For temporary issues
2. **📧 Contact Administrator**: For configuration problems
3. **⏰ Try Again Later**: For service availability issues
4. **📖 View Documentation**: For self-help solutions

## Read-Only Mode

### **What Happens**
When authentication fails, the plugin enters read-only mode:
- Interactive elements are disabled
- Clear error messages are displayed
- Recovery options are provided
- Visual indicators show the current state

### **How to Exit Read-Only Mode**
1. **Resolve the underlying issue** (usually requires admin action)
2. **Refresh the page** to re-attempt authentication
3. **Contact your administrator** if the issue persists

## Getting Help

### **Immediate Assistance**
- **Error Messages**: Always include specific guidance
- **Recovery Actions**: Step-by-step solutions provided
- **Admin Contact**: Direct contact information displayed

### **Administrator Contact**
- **Email**: Shown in error messages
- **Support URL**: Available in plugin settings
- **Documentation**: Comprehensive guides available

### **Self-Service Options**
- **Troubleshooting Guides**: Available in plugin documentation
- **FAQ Section**: Common questions and answers
- **Video Tutorials**: Step-by-step visual guides

## Best Practices

### **For Users**
1. **Keep your Moodle session active** while using the plugin
2. **Report authentication issues** to your administrator promptly
3. **Follow recovery instructions** provided in error messages
4. **Check plugin status** if you experience unusual behavior

### **For Administrators**
1. **Monitor error logs** for authentication issues
2. **Keep contact information** up to date
3. **Review subscription status** regularly
4. **Update site URLs** if your site is moved

## Troubleshooting

### **Plugin Won't Load**
1. Check if you're logged into Moodle
2. Verify you have the required permissions
3. Check for authentication error messages
4. Contact your administrator if issues persist

### **Getting Error Messages**
1. Read the error message carefully
2. Follow the provided recovery steps
3. Check the suggestions section
4. Contact your administrator if needed

### **Slow Performance**
1. Check your internet connection
2. Verify the plugin service is accessible
3. Clear your browser cache
4. Contact support if performance issues persist

## Security Features

### **What's Protected**
- **API Key Security**: Keys are encrypted and cannot be viewed
- **Site Validation**: Only registered sites can access the service
- **User Verification**: Email addresses are validated against admin records
- **Request Logging**: All authentication attempts are logged

### **What's Not Protected**
- **Moodle Session**: Plugin relies on Moodle's authentication
- **Browser Security**: Local storage is used for performance
- **Network Security**: HTTPS is required for secure communication

## Performance Optimization

### **Caching System**
- **API Key Caching**: Reduces validation overhead
- **Local Storage**: Improves frontend performance
- **Database Optimization**: Minimizes query impact
- **Response Time**: Target <100ms for all operations

### **Load Handling**
- **Concurrent Users**: Supports 1000+ simultaneous users
- **Memory Usage**: Optimized for minimal resource consumption
- **Scalability**: Designed for enterprise-level usage

## Support Information

### **Technical Support**
- **Email**: Available in plugin settings
- **Documentation**: Comprehensive guides and tutorials
- **Community**: User forums and discussion groups

### **Emergency Contact**
- **Critical Issues**: Immediate response required
- **Service Outages**: Status updates and notifications
- **Security Issues**: Priority handling and resolution

## Version Information

- **Current Version**: v1.5.0
- **Last Updated**: December 19, 2024
- **Compatibility**: Moodle 3.9+
- **PHP Version**: 7.4+

## Changelog

### **v1.5.0 (Latest)**
- ✅ Comprehensive testing and optimization
- ✅ Performance benchmarks achieved
- ✅ Security testing completed
- ✅ Load testing for 1000+ users

### **v1.4.0**
- ✅ Professional error handling system
- ✅ User-friendly notification system
- ✅ Recovery action system
- ✅ Accessibility improvements

### **v1.3.0**
- ✅ Token-based authentication
- ✅ Site URL validation
- ✅ User email verification
- ✅ Read-only mode implementation

## Need More Help?

If you need additional assistance:
1. **Check the error messages** for specific guidance
2. **Follow recovery actions** provided in the interface
3. **Contact your administrator** for configuration issues
4. **Review documentation** for detailed information
5. **Use support channels** for technical assistance

---

*This guide is part of the Adeptus Insights plugin documentation. For the latest updates, check your plugin's documentation section.*
