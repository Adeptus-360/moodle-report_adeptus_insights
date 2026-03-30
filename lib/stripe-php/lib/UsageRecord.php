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

namespace Stripe;

/**
 * Usage records allow you to report customer usage and metrics to Stripe for
 * metered billing of subscription prices.
 *
 * Related guide: <a href="https://stripe.com/docs/billing/subscriptions/metered-billing">Metered billing</a>
 *
 * This is our legacy usage-based billing API. See the <a href="https://docs.stripe.com/billing/subscriptions/usage-based">updated usage-based billing docs</a>.
 *
 * @property string $id Unique identifier for the object.
 * @property string $object String representing the object's type. Objects of the same type share the same value.
 * @property bool $livemode Has the value <code>true</code> if the object exists in live mode or the value <code>false</code> if the object exists in test mode.
 * @property int $quantity The usage quantity for the specified date.
 * @property string $subscription_item The ID of the subscription item this usage record contains data for.
 * @property int $timestamp The timestamp when this usage occurred.
 * @package report_adeptus_insights
 */
class UsageRecord extends ApiResource
{
    const OBJECT_NAME = 'usage_record';
}
