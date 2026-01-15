# Adeptus Insights

AI-powered insights and analytics report plugin for Moodle.

## Description

Adeptus Insights is a Moodle report plugin that provides AI-powered analytics and custom report generation capabilities. The plugin features an intelligent AI Assistant that helps users create, modify, and analyze reports using natural language queries.

### Key Features

- **AI Assistant**: Generate custom SQL reports by describing what you need in plain English
- **Report Wizard**: Create reports using an intuitive step-by-step interface
- **Generated Reports**: Save, manage, and re-run your custom reports
- **Subscription Management**: Flexible subscription tiers with usage tracking
- **Export Capabilities**: Export reports to PDF, CSV, and JSON formats
- **Interactive Data Tables**: Sort, filter, and search through report results
- **Chart Visualizations**: Visualize data with various chart types

## Requirements

- **Moodle**: Version 4.1 or higher (2022112800)
- **PHP**: Version 7.4 or higher (8.1+ recommended)

## Installation

### Method 1: Upload via Moodle Admin

1. Download the plugin ZIP file
2. Log in to your Moodle site as an administrator
3. Go to **Site administration > Plugins > Install plugins**
4. Upload the ZIP file and follow the installation prompts
5. Complete the plugin configuration

### Method 2: Manual Installation

1. Download and extract the plugin
2. Copy the `adeptus_insights` folder to `/path/to/moodle/report/`
3. Log in as administrator and visit the notifications page
4. Follow the installation prompts
5. Configure the plugin settings

## Configuration

After installation, configure the plugin at:

**Site administration > Reports > Adeptus Insights**

### Required Settings

1. **API Key**: Enter your Adeptus 360 API key to enable AI features
2. **Site Registration**: Register your site to activate the subscription

### Optional Settings

- Customize report categories
- Configure export options
- Set up user permissions

## Usage

### Accessing the Plugin

Navigate to **Reports > Adeptus Insights** from the site administration menu or course administration.

### AI Assistant

1. Go to the **AI Assistant** tab
2. Type your report request in natural language (e.g., "Show me all users who logged in this week")
3. Review the generated report
4. Save, modify, or export the results

### Report Wizard

1. Go to the **Report Wizard** tab
2. Select a report category
3. Choose from pre-built report templates
4. Customize parameters as needed
5. Generate and save your report

### Generated Reports

1. Go to the **Generated Reports** tab
2. View all your saved reports
3. Re-run reports with updated data
4. Export reports to PDF, CSV, or JSON

## Capabilities

The plugin defines the following capability:

- `report/adeptus_insights:view` - View and use the Adeptus Insights reports (granted to Manager and Teacher archetypes by default)

## Privacy and Data Security

### Your Data Stays On Your Server

Adeptus Insights is designed with data sovereignty as a core principle. **Your student data, grades, activity logs, and report results never leave your Moodle server.**

Here's how it works:

1. You ask a question in natural language (e.g., "Show students at risk of failing")
2. Our AI interprets your request and generates secure SQL instructions
3. The SQL runs **locally on your Moodle database**
4. Results are displayed directly to you - they are never transmitted externally

### What We Store

**On your Moodle server (stays with you):**
- All student and course data
- Generated report results and analytics
- Report configurations and bookmarks
- Export files

**On Adeptus 360 servers (for service operation only):**
- Administrator contact details (email, name) for registration
- Site URL for license validation
- Subscription and billing information

**We do NOT have access to:**
- Student personal information
- Grades or academic records
- Course content or activities
- Any data queried through reports

### GDPR Compliance

This plugin fully implements Moodle's Privacy API:
- Users can request export of their data
- Users can request deletion of their data
- All data handling complies with GDPR requirements

See the Privacy API implementation in `classes/privacy/provider.php` for technical details.

## Troubleshooting

### Common Issues

**AI Assistant not responding**
- Verify your API key is correctly configured
- Check that your site is properly registered
- Ensure you have an active subscription

**Reports not generating**
- Check user has the required capability (`report/adeptus_insights:view`)
- Verify database connectivity
- Check PHP memory limits for large reports

**Export not working**
- Ensure adequate PHP memory limit (512M recommended)
- Check max_execution_time setting (600 seconds recommended)

## Support

For technical support and documentation:

- **Website**: [www.adeptus360.com](https://www.adeptus360.com)
- **Email**: info@adeptus360.com

## License

This plugin is licensed under the GNU General Public License v3 or later.

See [http://www.gnu.org/copyleft/gpl.html](http://www.gnu.org/copyleft/gpl.html) for details.

## Author

**Adeptus 360**

- Website: [www.adeptus360.com](https://www.adeptus360.com)
- Email: info@adeptus360.com

## Version

- **Current Version**: 1.0.0
- **Moodle Compatibility**: 4.1+
- **Maturity**: Stable
