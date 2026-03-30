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

namespace Stripe\V2\Billing;

/**
 * @property string $id The unique id of this auth session.
 * @property string $object String representing the object's type. Objects of the same type share the same value of the object field.
 * @property string $authentication_token The authentication token for this session.  Use this token when calling the high-throughput meter event API.
 * @property int $created The creation time of this session.
 * @property int $expires_at The time at which this session will expire.
 * @property bool $livemode Has the value <code>true</code> if the object exists in live mode or the value <code>false</code> if the object exists in test mode.
 * @package report_adeptus_insights
 */
class MeterEventSession extends \Stripe\ApiResource
{
    const OBJECT_NAME = 'v2.billing.meter_event_session';
}
