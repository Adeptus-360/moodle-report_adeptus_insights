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

use ArrayIterator;
use IteratorAggregate;

class Set implements IteratorAggregate
{
    private $_elts;

    public function __construct($members = []) {
        $this->_elts = [];
        foreach ($members as $item) {
            $this->_elts[$item] = true;
        }
    }

    public function includes($elt) {
        return isset($this->_elts[$elt]);
    }

    public function add($elt) {
        $this->_elts[$elt] = true;
    }

    public function discard($elt) {
        unset($this->_elts[$elt]);
    }

    public function toArray() {
        return \array_keys($this->_elts);
    }

    /**
     * @return ArrayIterator
     */
    #[\ReturnTypeWillChange]
    public function getIterator() {
        return new ArrayIterator($this->toArray());
    }
}
