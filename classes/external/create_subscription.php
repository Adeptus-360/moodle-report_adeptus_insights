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

namespace report_adeptus_insights\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;
use context_system;

/**
 * External service to create a subscription.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class create_subscription extends external_api {
    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'plan_id' => new external_value(PARAM_INT, 'Plan ID to subscribe to'),
            'payment_method_id' => new external_value(PARAM_TEXT, 'Stripe payment method ID'),
            'billing_email' => new external_value(PARAM_EMAIL, 'Billing email address'),
        ]);
    }

    /**
     * Create a subscription.
     *
     * @param int $planid Plan ID
     * @param string $paymentmethodid Payment method ID
     * @param string $billingemail Billing email
     * @return array Result
     */
    public static function execute(int $planid, string $paymentmethodid, string $billingemail): array {
        // Validate parameters.
        $params = self::validate_parameters(self::execute_parameters(), [
            'plan_id' => $planid,
            'payment_method_id' => $paymentmethodid,
            'billing_email' => $billingemail,
        ]);

        // Validate context.
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('report/adeptus_insights:view', $context);

        try {
            // Get installation manager.
            $installationmanager = new \report_adeptus_insights\installation_manager();

            // Check if installation is registered.
            if (!$installationmanager->is_registered()) {
                return [
                    'success' => false,
                    'error' => true,
                    'message' => get_string('not_registered', 'report_adeptus_insights'),
                    'subscription_id' => '',
                ];
            }

            // Create subscription.
            $result = $installationmanager->create_subscription(
                $params['plan_id'],
                $params['payment_method_id'],
                $params['billing_email']
            );

            if ($result['success']) {
                return [
                    'success' => true,
                    'error' => false,
                    'message' => $result['message'] ?? '',
                    'subscription_id' => $result['data']['subscription_id'] ?? '',
                ];
            } else {
                return [
                    'success' => false,
                    'error' => true,
                    'message' => $result['message'] ?? get_string('error_create_subscription', 'report_adeptus_insights'),
                    'subscription_id' => '',
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => true,
                'message' => get_string('error_occurred', 'report_adeptus_insights', $e->getMessage()),
                'subscription_id' => '',
            ];
        }
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the subscription was created'),
            'error' => new external_value(PARAM_BOOL, 'Whether an error occurred'),
            'message' => new external_value(PARAM_TEXT, 'Status message'),
            'subscription_id' => new external_value(PARAM_TEXT, 'New subscription ID'),
        ]);
    }
}
