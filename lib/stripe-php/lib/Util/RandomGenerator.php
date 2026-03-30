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

namespace Stripe\Util;

/**
 * A basic random generator. This is in a separate class so we the generator
 * can be injected as a dependency and replaced with a mock in tests.
 * @package report_adeptus_insights
 */
class RandomGenerator
{
    /**
     * Returns a random value between 0 and $max.
     *
     * @param float $max (optional)
     *
     * @return float
     */
    public function randFloat($max = 1.0) {
        return \mt_rand() / \mt_getrandmax() * $max;
    }

    /**
     * Returns a v4 UUID.
     *
     * @return string
     */
    public function uuid() {
        $arr = \array_values(\unpack('N1a/n4b/N1c', \openssl_random_pseudo_bytes(16)));
        $arr[2] = ($arr[2] & 0x0FFF) | 0x4000;
        $arr[3] = ($arr[3] & 0x3FFF) | 0x8000;

        return \vsprintf('%08x-%04x-%04x-%04x-%04x%08x', $arr);
    }
}
