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
 * Class RequestTelemetry.
 *
 * Tracks client request telemetry
 * @package report_adeptus_insights
 */
class RequestTelemetry
{
    /** @var string */
    public $requestId;
    /** @var int */
    public $requestDuration;
    /** @var string[] */
    public $usage;

    /**
     * Initialize a new telemetry object.
     *
     * @param string $requestId the request's request ID
     * @param int $requestDuration the request's duration in milliseconds
     * @param string[] $usage names of tracked behaviors associated with this request
     */
    public function __construct($requestId, $requestDuration, $usage = []) {
        $this->requestId = $requestId;
        $this->requestDuration = $requestDuration;
        $this->usage = $usage;
    }
}
