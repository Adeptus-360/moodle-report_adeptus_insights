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

namespace Stripe\Service\V2\Core;

/**
 * Service factory class for API resources in the root namespace.
 * // Doc: The beginning of the section generated from our OpenAPI spec.
 *
 * @property EventDestinationService $eventDestinations
 * @property EventService $events
 * // Doc: The end of the section generated from our OpenAPI spec
 * @package report_adeptus_insights
 */
class CoreServiceFactory extends \Stripe\Service\AbstractServiceFactory
{
    /**
     * @var array<string, string>
     */
    private static $classMap = [
        // Class Map: The beginning of the section generated from our OpenAPI spec
        'eventDestinations' => EventDestinationService::class,
        'events' => EventService::class,
        // Class Map: The end of the section generated from our OpenAPI spec
    ];

    protected function getServiceClass($name) {
        return \array_key_exists($name, self::$classMap) ? self::$classMap[$name] : null;
    }
}
