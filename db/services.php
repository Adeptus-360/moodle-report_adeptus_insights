<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
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
        'classpath'   => '',
        'description' => 'Send a message to the AI assistant',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities' => 'report/adeptus_insights:view',
    ],
    'report_adeptus_insights_get_history' => [
        'classname'   => 'report_adeptus_insights\external',
        'methodname'  => 'get_history',
        'classpath'   => '',
        'description' => 'Get chat history from the AI assistant',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => 'report/adeptus_insights:view',
    ],
    'report_adeptus_insights_get_subscription_details' => [
        'classname'   => 'report_adeptus_insights\external',
        'methodname'  => 'get_subscription_details',
        'classpath'   => '',
        'description' => 'Get subscription details for the current installation',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => 'report/adeptus_insights:view',
    ],
    'report_adeptus_insights_create_billing_portal_session' => [
        'classname'   => 'report_adeptus_insights\external',
        'methodname'  => 'create_billing_portal_session',
        'classpath'   => '',
        'description' => 'Create billing portal session for subscription management',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities' => 'report/adeptus_insights:view',
    ],
    'report_adeptus_insights_fetch_preview' => [
        'classname'   => 'report_adeptus_insights\external\fetch_preview',
        'methodname'  => 'execute',
        'classpath'   => '', // Autoloaded, no need for manual path
        'description' => 'Fetch preview data from report_adeptus_insights_analytics',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => 'moodle/site:viewreports',
    ],
    'report_adeptus_insights_get_students' => [
        'classname'   => 'report_adeptus_insights\\external\\fetch_students',
        'methodname'  => 'execute',
        'classpath'   => '',
        'description' => 'Get students by courseids',
        'type'        => 'read',
        'ajax'        => true,
    ],
    'report_adeptus_insights_create_billing_portal' => [
        'classname'   => 'report_adeptus_insights\external',
        'methodname'  => 'create_billing_portal',
        'classpath'   => '',
        'description' => 'Create billing portal session for subscription management',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities' => 'report/adeptus_insights:view',
    ],
    'report_adeptus_insights_create_product_portal_session' => [
        'classname'   => 'report_adeptus_insights\external',
        'methodname'  => 'create_product_portal_session',
        'classpath'   => '',
        'description' => 'Create billing portal session for specific product upgrade/downgrade',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities' => 'report/adeptus_insights:view',
    ],
    'report_adeptus_insights_register_installation' => [
        'classname'   => 'report_adeptus_insights\external',
        'methodname'  => 'register_installation',
        'classpath'   => '',
        'description' => 'Register a new installation of Adeptus Insights',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities' => 'report/adeptus_insights:view',
    ],
    'report_adeptus_insights_cancel_subscription' => [
        'classname'   => 'report_adeptus_insights\external',
        'methodname'  => 'cancel_subscription',
        'classpath'   => '',
        'description' => 'Cancel the current subscription',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities' => 'report/adeptus_insights:view',
    ],
    'report_adeptus_insights_activate_free_plan' => [
        'classname'   => 'report_adeptus_insights\external',
        'methodname'  => 'activate_free_plan',
        'classpath'   => '',
        'description' => 'Activate the free plan for the installation',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities' => 'report/adeptus_insights:view',
    ],
    'report_adeptus_insights_create_checkout_session' => [
        'classname'   => 'report_adeptus_insights\external',
        'methodname'  => 'create_checkout_session',
        'classpath'   => '',
        'description' => 'Create Stripe Checkout session for new subscriptions',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities' => 'report/adeptus_insights:view',
    ],
    'report_adeptus_insights_verify_checkout_session' => [
        'classname'   => 'report_adeptus_insights\external',
        'methodname'  => 'verify_checkout_session',
        'classpath'   => '',
        'description' => 'Verify completed Stripe checkout and update subscription',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities' => 'report/adeptus_insights:view',
    ],

    // Phase 1: Core Reporting External Services.
    'report_adeptus_insights_get_wizard_data' => [
        'classname'   => 'report_adeptus_insights\external\get_wizard_data',
        'methodname'  => 'execute',
        'classpath'   => '',
        'description' => 'Get wizard initialization data including user info and session key',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => 'report/adeptus_insights:view',
    ],
    'report_adeptus_insights_get_available_reports' => [
        'classname'   => 'report_adeptus_insights\external\get_available_reports',
        'methodname'  => 'execute',
        'classpath'   => '',
        'description' => 'Get available reports filtered by Moodle version compatibility',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => 'report/adeptus_insights:view',
    ],
    'report_adeptus_insights_get_wizard_reports' => [
        'classname'   => 'report_adeptus_insights\external\get_wizard_reports',
        'methodname'  => 'execute',
        'classpath'   => '',
        'description' => 'Get user saved wizard reports from backend',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => 'report/adeptus_insights:view',
    ],
    'report_adeptus_insights_get_report_parameters' => [
        'classname'   => 'report_adeptus_insights\external\get_report_parameters',
        'methodname'  => 'execute',
        'classpath'   => '',
        'description' => 'Get report parameters with dynamically populated options',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => 'report/adeptus_insights:view',
    ],
    'report_adeptus_insights_generate_report' => [
        'classname'   => 'report_adeptus_insights\external\generate_report',
        'methodname'  => 'execute',
        'classpath'   => '',
        'description' => 'Generate a report by executing SQL query with parameters',
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
            'report_adeptus_insights_create_billing_portal',
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
        ],
        'restrictedusers' => 0,
        'enabled' => 1,
    ],
];
