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

namespace Stripe\FinancialConnections;

/**
 * Describes an owner of an account.
 *
 * @property string $id Unique identifier for the object.
 * @property string $object String representing the object's type. Objects of the same type share the same value.
 * @property null|string $email The email address of the owner.
 * @property string $name The full name of the owner.
 * @property string $ownership The ownership object that this owner belongs to.
 * @property null|string $phone The raw phone number of the owner.
 * @property null|string $raw_address The raw physical address of the owner.
 * @property null|int $refreshed_at The timestamp of the refresh that updated this owner.
 * @package report_adeptus_insights
 */
class AccountOwner extends \Stripe\ApiResource
{
    const OBJECT_NAME = 'financial_connections.account_owner';
}
