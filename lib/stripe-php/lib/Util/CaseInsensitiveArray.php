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
 * CaseInsensitiveArray is an array-like class that ignores case for keys.
 *
 * It is used to store HTTP headers. Per RFC 2616, section 4.2:
 * Each header field consists of a name followed by a colon (":") and the field value. Field names
 * are case-insensitive.
 *
 * In the context of stripe-php, this is useful because the API will return headers with different
 * case depending on whether HTTP/2 is used or not (with HTTP/2, headers are always in lowercase).
 * @package report_adeptus_insights
 */
class CaseInsensitiveArray implements \ArrayAccess, \Countable, \IteratorAggregate
{
    private $container = [];

    public function __construct($initial_array = []) {
        $this->container = \array_change_key_case($initial_array, \CASE_LOWER);
    }

    /**
     * @return int
     */
    #[\ReturnTypeWillChange]
    public function count() {
        return \count($this->container);
    }

    /**
     * @return \ArrayIterator
     */
    #[\ReturnTypeWillChange]
    public function getIterator() {
        return new \ArrayIterator($this->container);
    }

    /**
     * @return void
     */
    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value) {
        $offset = self::maybeLowercase($offset);
        if (null === $offset) {
            $this->container[] = $value;
        } else {
            $this->container[$offset] = $value;
        }
    }

    /**
     * @return bool
     */
    #[\ReturnTypeWillChange]
    public function offsetExists($offset) {
        $offset = self::maybeLowercase($offset);

        return isset($this->container[$offset]);
    }

    /**
     * @return void
     */
    #[\ReturnTypeWillChange]
    public function offsetUnset($offset) {
        $offset = self::maybeLowercase($offset);
        unset($this->container[$offset]);
    }

    /**
     * @return mixed
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset) {
        $offset = self::maybeLowercase($offset);

        return isset($this->container[$offset]) ? $this->container[$offset] : null;
    }

    private static function maybeLowercase($v) {
        if (\is_string($v)) {
            return \strtolower($v);
        }

        return $v;
    }
}
