<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

// File generated from our OpenAPI spec

namespace Stripe\Billing;

/**
 * @property string $object String representing the object's type. Objects of the same type share the same value.
 * @property \Stripe\Billing\Alert $alert A billing alert is a resource that notifies you when a certain usage threshold on a meter is crossed. For example, you might create a billing alert to notify you when a certain user made 100 API requests.
 * @property int $created Time at which the object was created. Measured in seconds since the Unix epoch.
 * @property string $customer ID of customer for which the alert triggered
 * @property bool $livemode Has the value <code>true</code> if the object exists in live mode or the value <code>false</code> if the object exists in test mode.
 * @property int $value The value triggering the alert
 * @package report_adeptus_insights
 */
class AlertTriggered extends \Stripe\ApiResource
{
    const OBJECT_NAME = 'billing.alert_triggered';
}
