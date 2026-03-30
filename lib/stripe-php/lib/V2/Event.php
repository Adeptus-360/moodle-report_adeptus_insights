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

namespace Stripe\V2;

/**
 * @property string $id Unique identifier for the event.
 * @property string $object String representing the object's type. Objects of the same type share the same value of the object field.
 * @property int $created Time at which the object was created.
 * @property \Stripe\StripeObject $reason Reason for the event.
 * @property string $type The type of the event.
 * @property null|string $context The Stripe account of the event
 * @package report_adeptus_insights
 */
abstract class Event extends \Stripe\ApiResource
{
    const OBJECT_NAME = 'v2.core.event';
}
