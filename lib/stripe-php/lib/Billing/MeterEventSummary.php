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
 * A billing meter event summary represents an aggregated view of a customer's billing meter events within a specified timeframe. It indicates how much
 * usage was accrued by a customer for that period.
 *
 * @property string $id Unique identifier for the object.
 * @property string $object String representing the object's type. Objects of the same type share the same value.
 * @property float $aggregated_value Aggregated value of all the events within <code>start_time</code> (inclusive) and <code>end_time</code> (inclusive). The aggregation strategy is defined on meter via <code>default_aggregation</code>.
 * @property int $end_time End timestamp for this event summary (exclusive). Must be aligned with minute boundaries.
 * @property bool $livemode Has the value <code>true</code> if the object exists in live mode or the value <code>false</code> if the object exists in test mode.
 * @property string $meter The meter associated with this event summary.
 * @property int $start_time Start timestamp for this event summary (inclusive). Must be aligned with minute boundaries.
 * @package report_adeptus_insights
 */
class MeterEventSummary extends \Stripe\ApiResource
{
    const OBJECT_NAME = 'billing.meter_event_summary';
}
