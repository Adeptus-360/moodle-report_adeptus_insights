<?php
// This file is part of Moodle - http://moodle.org/.
//
// Moodle is free software: you can redistribute it and/or modify.
// it under the terms of the GNU General Public License as published by.
// the Free Software Foundation, either version 3 of the License, or.
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,.
// but WITHOUT ANY WARRANTY; without even the implied warranty of.
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the.
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License.
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * External services definition for Adeptus Insights.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'report_adeptus_insights_send_message' => [
        'classname'   => 'report_adeptus_insights\external',
        'methodname'  => 'send_message',
        'classpath'   => 'report/adeptus_insights/externallib.php',
        'description' => 'Send a message to the AI assistant',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities' => 'report/adeptus_insights:view',
    ],
    'report_adeptus_insights_get_history' => [
        'classname'   => 'report_adeptus_insights\external',
        'methodname'  => 'get_history',
        'classpath'   => 'report/adeptus_insights/externallib.php',
        'description' => 'Get chat history from the AI assistant',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => 'report/adeptus_insights:view',
    ],
    'report_adeptus_insights_get_subscription_details' => [
        'classname'   => 'report_adeptus_insights\external',
        'methodname'  => 'get_subscription_details',
        'classpath'   => 'report/adeptus_insights/externallib.php',
        'description' => 'Get subscription details for the current installation',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => 'report/adeptus_insights:view',
    ],
    'report_adeptus_insights_create_billing_portal_session' => [
        'classname'   => 'report_adeptus_insights\external',
        'methodname'  => 'create_billing_portal_session',
        'classpath'   => 'report/adeptus_insights/externallib.php',
        'description' => 'Create billing portal session for subscription management',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities' => 'report/adeptus_insights:view',
    ],
    'report_adeptus_insights_fetch_preview' => [
        'classname'   => 'report_adeptus_insights\external\fetch_preview',
        'methodname'  => 'execute',
        'description' => 'Fetch preview data from report_adeptus_insights_analytics',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => 'moodle/site:viewreports',
    ],
    'report_adeptus_insights_get_students' => [
        'classname'   => 'report_adeptus_insights\external\fetch_students',
        'methodname'  => 'execute',
        'description' => 'Get students by courseids',
        'type'        => 'read',
        'ajax'        => true,
    ],
    'report_adeptus_insights_create_product_portal_session' => [
        'classname'   => 'report_adeptus_insights\external',
        'methodname'  => 'create_product_portal_session',
        'classpath'   => 'report/adeptus_insights/externallib.php',
        'description' => 'Create billing portal session for specific product upgrade/downgrade',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities' => 'report/adeptus_insights:view',
    ],
    'report_adeptus_insights_register_installation' => [
        'classname'   => 'report_adeptus_insights\external',
        'methodname'  => 'register_installation',
        'classpath'   => 'report/adeptus_insights/externallib.php',
        'description' => 'Register a new installation of Adeptus Insights',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities' => 'report/adeptus_insights:view',
    ],
    'report_adeptus_insights_cancel_subscription' => [
        'classname'   => 'report_adeptus_insights\external',
        'methodname'  => 'cancel_subscription',
        'classpath'   => 'report/adeptus_insights/externallib.php',
        'description' => 'Cancel the current subscription',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities' => 'report/adeptus_insights:view',
    ],
    'report_adeptus_insights_activate_free_plan' => [
        'classname'   => 'report_adeptus_insights\external',
        'methodname'  => 'activate_free_plan',
        'classpath'   => 'report/adeptus_insights/externallib.php',
        'description' => 'Activate the free plan for the installation',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities' => 'report/adeptus_insights:view',
    ],
    'report_adeptus_insights_create_checkout_session' => [
        'classname'   => 'report_adeptus_insights\external',
        'methodname'  => 'create_checkout_session',
        'classpath'   => 'report/adeptus_insights/externallib.php',
        'description' => 'Create Stripe Checkout session for new subscriptions',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities' => 'report/adeptus_insights:view',
    ],
    'report_adeptus_insights_verify_checkout_session' => [
        'classname'   => 'report_adeptus_insights\external',
        'methodname'  => 'verify_checkout_session',
        'classpath'   => 'report/adeptus_insights/externallib.php',
        'description' => 'Verify completed Stripe checkout and update subscription',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities' => 'report/adeptus_insights:view',
    ],

    // Phase 1: Core Reporting External Services.
    'report_adeptus_insights_get_wizard_data' => [
        'classname'   => 'report_adeptus_insights\external\get_wizard_data',
        'methodname'  => 'execute',
        'description' => 'Get wizard initialization data including user info and session key',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => 'report/adeptus_insights:view',
    ],
    'report_adeptus_insights_get_available_reports' => [
        'classname'   => 'report_adeptus_insights\external\get_available_reports',
        'methodname'  => 'execute',
        'description' => 'Get available reports filtered by Moodle version compatibility',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => 'report/adeptus_insights:view',
    ],
    'report_adeptus_insights_get_wizard_reports' => [
        'classname'   => 'report_adeptus_insights\external\get_wizard_reports',
        'methodname'  => 'execute',
        'description' => 'Get user saved wizard reports from backend',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => 'report/adeptus_insights:view',
    ],
    'report_adeptus_insights_get_report_parameters' => [
        'classname'   => 'report_adeptus_insights\external\get_report_parameters',
        'methodname'  => 'execute',
        'description' => 'Get report parameters with dynamically populated options',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => 'report/adeptus_insights:view',
    ],
    'report_adeptus_insights_generate_report' => [
        'classname'   => 'report_adeptus_insights\external\generate_report',
        'methodname'  => 'execute',
        'description' => 'Generate a report by executing SQL query with parameters',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => 'report/adeptus_insights:view',
    ],

    // Phase 2: Subscription & Eligibility External Services.
    'report_adeptus_insights_check_subscription_status' => [
        'classname'   => 'report_adeptus_insights\external\check_subscription_status',
        'methodname'  => 'execute',
        'description' => 'Check subscription status for the current installation',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => 'report/adeptus_insights:view',
    ],
    'report_adeptus_insights_check_report_eligibility' => [
        'classname'   => 'report_adeptus_insights\external\check_report_eligibility',
        'methodname'  => 'execute',
        'description' => 'Check if user is eligible to create reports',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => 'report/adeptus_insights:view',
    ],
    'report_adeptus_insights_check_export_eligibility' => [
        'classname'   => 'report_adeptus_insights\external\check_export_eligibility',
        'methodname'  => 'execute',
        'description' => 'Check if user is eligible to export in specified format',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => 'report/adeptus_insights:view',
    ],
    'report_adeptus_insights_get_usage_data' => [
        'classname'   => 'report_adeptus_insights\external\get_usage_data',
        'methodname'  => 'execute',
        'description' => 'Get usage data for the current installation',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => 'report/adeptus_insights:view',
    ],
    'report_adeptus_insights_get_available_plans' => [
        'classname'   => 'report_adeptus_insights\external\get_available_plans',
        'methodname'  => 'execute',
        'description' => 'Get available subscription plans',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => 'report/adeptus_insights:view',
    ],
    'report_adeptus_insights_create_subscription' => [
        'classname'   => 'report_adeptus_insights\external\create_subscription',
        'methodname'  => 'execute',
        'description' => 'Create a new subscription',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities' => 'report/adeptus_insights:view',
    ],

    // Phase 3: AI Report Execution.
    'report_adeptus_insights_execute_ai_report' => [
        'classname'   => 'report_adeptus_insights\external\execute_ai_report',
        'methodname'  => 'execute',
        'description' => 'Execute AI-generated report SQL locally',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => 'report/adeptus_insights:view',
    ],

    // Backend API Reports.
    'report_adeptus_insights_get_reports_from_backend' => [
        'classname'   => 'report_adeptus_insights\external\get_reports_from_backend',
        'methodname'  => 'execute',
        'description' => 'Get reports from backend API filtered by Moodle version',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => 'report/adeptus_insights:view',
    ],
    'report_adeptus_insights_proxy_backend_request' => [
        'classname'   => 'report_adeptus_insights\external\proxy_backend_request',
        'methodname'  => 'execute',
        'description' => 'Server-side proxy for backend API requests (avoids CORS)',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities' => 'report/adeptus_insights:view',
    ],
    'report_adeptus_insights_get_ai_reports' => [
        'classname'   => 'report_adeptus_insights\external\get_ai_reports',
        'methodname'  => 'execute',
        'description' => 'Get AI-generated reports from backend (server-side proxy)',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => 'report/adeptus_insights:view',
    ],
    'report_adeptus_insights_get_auth_status' => [
        'classname'   => 'report_adeptus_insights\external\get_auth_status',
        'methodname'  => 'execute',
        'description' => 'Get authentication status for current user',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => 'report/adeptus_insights:view',
    ],
    'report_adeptus_insights_batch_kpi_data' => [
        'classname'   => 'report_adeptus_insights\external\batch_kpi_data',
        'methodname'  => 'execute',
        'description' => 'Fetch batch KPI data for multiple reports',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => 'report/adeptus_insights:view',
    ],

];

$services = [
    'Adeptus Insights internal service' => [
        'shortname' => 'adeptus_insights_internal',
        'functions' => [
            'report_adeptus_insights_send_message',
            'report_adeptus_insights_get_history',
            'report_adeptus_insights_get_subscription_details',
            'report_adeptus_insights_create_billing_portal_session',
            'report_adeptus_insights_fetch_preview',
            'report_adeptus_insights_get_students',
            'report_adeptus_insights_create_product_portal_session',
            'report_adeptus_insights_register_installation',
            'report_adeptus_insights_cancel_subscription',
            'report_adeptus_insights_activate_free_plan',
            'report_adeptus_insights_create_checkout_session',
            'report_adeptus_insights_verify_checkout_session',
            // Phase 1: Core Reporting.
            'report_adeptus_insights_get_wizard_data',
            'report_adeptus_insights_get_available_reports',
            'report_adeptus_insights_get_wizard_reports',
            'report_adeptus_insights_get_report_parameters',
            'report_adeptus_insights_generate_report',
            // Phase 2: Subscription & Eligibility.
            'report_adeptus_insights_check_subscription_status',
            'report_adeptus_insights_check_report_eligibility',
            'report_adeptus_insights_check_export_eligibility',
            'report_adeptus_insights_get_usage_data',
            'report_adeptus_insights_get_available_plans',
            'report_adeptus_insights_create_subscription',
            // Phase 3: AI Report Execution.
            'report_adeptus_insights_execute_ai_report',
            // Backend API Reports.
            'report_adeptus_insights_get_reports_from_backend',
            'report_adeptus_insights_proxy_backend_request',
            'report_adeptus_insights_get_ai_reports',
            'report_adeptus_insights_get_auth_status',
            'report_adeptus_insights_batch_kpi_data',
        ],
        'restrictedusers' => 0,
        'enabled' => 1,
    ],
];
