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

namespace Stripe\Treasury;

/**
 * ReceivedDebits represent funds pulled from a <a href="https://stripe.com/docs/api#financial_accounts">FinancialAccount</a>. These are not initiated from the FinancialAccount.
 *
 * @property string $id Unique identifier for the object.
 * @property string $object String representing the object's type. Objects of the same type share the same value.
 * @property int $amount Amount (in cents) transferred.
 * @property int $created Time at which the object was created. Measured in seconds since the Unix epoch.
 * @property string $currency Three-letter <a href="https://www.iso.org/iso-4217-currency-codes.html">ISO currency code</a>, in lowercase. Must be a <a href="https://stripe.com/docs/currencies">supported currency</a>.
 * @property string $description An arbitrary string attached to the object. Often useful for displaying to users.
 * @property null|string $failure_code Reason for the failure. A ReceivedDebit might fail because the FinancialAccount doesn't have sufficient funds, is closed, or is frozen.
 * @property null|string $financial_account The FinancialAccount that funds were pulled from.
 * @property null|string $hosted_regulatory_receipt_url A <a href="https://stripe.com/docs/treasury/moving-money/regulatory-receipts">hosted transaction receipt</a> URL that is provided when money movement is considered regulated under Stripe's money transmission licenses.
 * @property null|\Stripe\StripeObject $initiating_payment_method_details
 * @property \Stripe\StripeObject $linked_flows
 * @property bool $livemode Has the value <code>true</code> if the object exists in live mode or the value <code>false</code> if the object exists in test mode.
 * @property string $network The network used for the ReceivedDebit.
 * @property null|\Stripe\StripeObject $reversal_details Details describing when a ReceivedDebit might be reversed.
 * @property string $status Status of the ReceivedDebit. ReceivedDebits are created with a status of either <code>succeeded</code> (approved) or <code>failed</code> (declined). The failure reason can be found under the <code>failure_code</code>.
 * @property null|string|\Stripe\Treasury\Transaction $transaction The Transaction associated with this object.
 * @package report_adeptus_insights
 */
class ReceivedDebit extends \Stripe\ApiResource
{
    const OBJECT_NAME = 'treasury.received_debit';

    const FAILURE_CODE_ACCOUNT_CLOSED = 'account_closed';
    const FAILURE_CODE_ACCOUNT_FROZEN = 'account_frozen';
    const FAILURE_CODE_INSUFFICIENT_FUNDS = 'insufficient_funds';
    const FAILURE_CODE_INTERNATIONAL_TRANSACTION = 'international_transaction';
    const FAILURE_CODE_OTHER = 'other';

    const NETWORK_ACH = 'ach';
    const NETWORK_CARD = 'card';
    const NETWORK_STRIPE = 'stripe';

    const STATUS_FAILED = 'failed';
    const STATUS_SUCCEEDED = 'succeeded';

    /**
     * Returns a list of ReceivedDebits.
     *
     * @param null|array $params
     * @param null|array|string $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\Collection<\Stripe\Treasury\ReceivedDebit> of ApiResources
     */
    public static function all($params = null, $opts = null) {
        $url = static::classUrl();

        return static::_requestPage($url, \Stripe\Collection::class, $params, $opts);
    }

    /**
     * Retrieves the details of an existing ReceivedDebit by passing the unique
     * ReceivedDebit ID from the ReceivedDebit list.
     *
     * @param array|string $id the ID of the API resource to retrieve, or an options array containing an `id` key
     * @param null|array|string $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\Treasury\ReceivedDebit
     */
    public static function retrieve($id, $opts = null) {
        $opts = \Stripe\Util\RequestOptions::parse($opts);
        $instance = new static($id, $opts);
        $instance->refresh();

        return $instance;
    }
}
