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

namespace Stripe\Exception;

/**
 * UnknownApiErrorException is thrown when the client library receives an
 * error from the API it doesn't know about. Receiving this error usually
 * means that your client library is outdated and should be upgraded.
 * @package report_adeptus_insights
 */
class UnknownApiErrorException extends ApiErrorException
{
}
