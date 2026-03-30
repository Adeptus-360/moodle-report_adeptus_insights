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

namespace Stripe\ApiOperations;

/**
 * Trait for retrievable singleton resources. Adds a `retrieve()` static method to the
 * class.
 *
 * This trait should only be applied to classes that derive from SingletonApiResource.
 * @package report_adeptus_insights
 */
trait SingletonRetrieve
{
    /**
     * @param null|array|string $opts the ID of the API resource to retrieve,
     *     or an options array containing an `id` key
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return static
     */
    public static function retrieve($opts = null) {
        $opts = \Stripe\Util\RequestOptions::parse($opts);
        $instance = new static(null, $opts);
        $instance->refresh();

        return $instance;
    }
}
