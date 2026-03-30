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
 * A usage record summary represents an aggregated view of how much usage was accrued for a subscription item within a subscription billing period.
 *
 * @property string $id Unique identifier for the object.
 * @property string $object String representing the object's type. Objects of the same type share the same value.
 * @property null|string $invoice The invoice in which this usage period has been billed for.
 * @property bool $livemode Has the value <code>true</code> if the object exists in live mode or the value <code>false</code> if the object exists in test mode.
 * @property \Stripe\StripeObject $period
 * @property string $subscription_item The ID of the subscription item this summary is describing.
 * @property int $total_usage The total usage within this usage period.
 * @package report_adeptus_insights
 */
class UsageRecordSummary extends ApiResource
{
    const OBJECT_NAME = 'usage_record_summary';
}
