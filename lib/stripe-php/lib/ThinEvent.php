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

namespace Stripe;

/**
 * ThinEvent represents the json that's delivered from an Event Destination.
 * It's a basic class with no additional methods or properties. Use it to check basic information about a delivered event.
 * If you want more details, use `$stripeClient->v2->core->events->retrieve(thin_event.id)` to fetch the full event object.
 *
 * @property string             $id       Unique identifier for the event.
 * @property string             $type     The type of the event.
 * @property string             $created  Time at which the object was created.
 * @property null|string        $context  Authentication context needed to fetch the event or related object.
 * @property null|RelatedObject $related_object Object containing the reference to API resource relevant to the event.
 * @property null|Reason $reason Reason for the event.
 * @property bool $livemode Livemode indicates if the event is from a production(true) or test(false) account.
 * @package report_adeptus_insights
 */
class ThinEvent
{
    public $id;
    public $type;
    public $created;
    public $context;
    public $related_object;
    public $reason;
    public $livemode;
}
