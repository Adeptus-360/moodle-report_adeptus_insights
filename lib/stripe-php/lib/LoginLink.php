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

// File generated from our OpenAPI spec

namespace Stripe;

/**
 * Login Links are single-use URLs for a connected account to access the Express Dashboard. The connected account's <a href="/api/accounts/object#account_object-controller-stripe_dashboard-type">account.controller.stripe_dashboard.type</a> must be <code>express</code> to have access to the Express Dashboard.
 *
 * @property string $object String representing the object's type. Objects of the same type share the same value.
 * @property int $created Time at which the object was created. Measured in seconds since the Unix epoch.
 * @property string $url The URL for the login link.
 * @package report_adeptus_insights
 */
class LoginLink extends ApiResource
{
    const OBJECT_NAME = 'login_link';
}
