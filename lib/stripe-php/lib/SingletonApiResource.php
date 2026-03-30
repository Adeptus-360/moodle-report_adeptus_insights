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
 * Class SingletonApiResource.
 * @package report_adeptus_insights
 */
abstract class SingletonApiResource extends ApiResource
{
    /**
     * @return string the endpoint associated with this singleton class
     */
    public static function classUrl() {
        // Replace dots with slashes for namespaced resources, e.g. if the object's name is
        // "foo.bar", then its URL will be "/v1/foo/bar".

        /** @phpstan-ignore-next-line */
        $base = \str_replace('.', '/', static::OBJECT_NAME);

        return "/v1/{$base}";
    }

    /**
     * @return string the endpoint associated with this singleton API resource
     */
    public function instanceUrl() {
        return static::classUrl();
    }
}
