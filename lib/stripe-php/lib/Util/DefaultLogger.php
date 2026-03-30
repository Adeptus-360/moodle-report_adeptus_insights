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
 * A very basic implementation of LoggerInterface that has just enough
 * functionality that it can be the default for this library.
 * @package report_adeptus_insights
 */
class DefaultLogger implements LoggerInterface
{
    /** @var int */
    public $messageType = 0;

    /** @var null|string */
    public $destination;

    public function error($message, array $context = []) {
        if (\count($context) > 0) {
            throw new \Stripe\Exception\BadMethodCallException('DefaultLogger does not currently implement context. Please implement if you need it.');
        }

        if (null === $this->destination) {
            \error_log($message, $this->messageType);
        } else {
            \error_log($message, $this->messageType, $this->destination);
        }
    }
}
