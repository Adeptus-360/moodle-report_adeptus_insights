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
 * Class RecipientTransfer.
 *
 * @property string $id
 * @property string $object
 * @property int $amount
 * @property int $amount_reversed
 * @property string $balance_transaction
 * @property string $bank_account
 * @property string $card
 * @property int $created
 * @property string $currency
 * @property int $date
 * @property string $description
 * @property string $destination
 * @property string $failure_code
 * @property string $failure_message
 * @property bool $livemode
 * @property \Stripe\StripeObject $metadata
 * @property string $method
 * @property string $recipient
 * @property \Stripe\Collection $reversals
 * @property bool $reversed
 * @property string $source_type
 * @property string $statement_descriptor
 * @property string $status
 * @property string $type
 * @package report_adeptus_insights
 */
class RecipientTransfer extends ApiResource
{
    const OBJECT_NAME = 'recipient_transfer';
}
