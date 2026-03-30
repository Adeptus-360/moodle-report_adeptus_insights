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
 * Encodes whether a FinancialAccount has access to a particular Feature, with a <code>status</code> enum and associated <code>status_details</code>.
 * Stripe or the platform can control Features via the requested field.
 *
 * @property string $object String representing the object's type. Objects of the same type share the same value.
 * @property null|\Stripe\StripeObject $card_issuing Toggle settings for enabling/disabling a feature
 * @property null|\Stripe\StripeObject $deposit_insurance Toggle settings for enabling/disabling a feature
 * @property null|\Stripe\StripeObject $financial_addresses Settings related to Financial Addresses features on a Financial Account
 * @property null|\Stripe\StripeObject $inbound_transfers InboundTransfers contains inbound transfers features for a FinancialAccount.
 * @property null|\Stripe\StripeObject $intra_stripe_flows Toggle settings for enabling/disabling a feature
 * @property null|\Stripe\StripeObject $outbound_payments Settings related to Outbound Payments features on a Financial Account
 * @property null|\Stripe\StripeObject $outbound_transfers OutboundTransfers contains outbound transfers features for a FinancialAccount.
 * @package report_adeptus_insights
 */
class FinancialAccountFeatures extends \Stripe\ApiResource
{
    const OBJECT_NAME = 'treasury.financial_account_features';
}
