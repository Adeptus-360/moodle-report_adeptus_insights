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
 * Invoice Items represent the component lines of an <a href="https://stripe.com/docs/api/invoices">invoice</a>. An invoice item is added to an
 * invoice by creating or updating it with an <code>invoice</code> field, at which point it will be included as
 * <a href="https://stripe.com/docs/api/invoices/line_item">an invoice line item</a> within
 * <a href="https://stripe.com/docs/api/invoices/object#invoice_object-lines">invoice.lines</a>.
 *
 * Invoice Items can be created before you are ready to actually send the invoice. This can be particularly useful when combined
 * with a <a href="https://stripe.com/docs/api/subscriptions">subscription</a>. Sometimes you want to add a charge or credit to a customer, but actually charge
 * or credit the customer’s card only at the end of a regular billing cycle. This is useful for combining several charges
 * (to minimize per-transaction fees), or for having Stripe tabulate your usage-based billing totals.
 *
 * Related guides: <a href="https://stripe.com/docs/invoicing/integration">Integrate with the Invoicing API</a>, <a href="https://stripe.com/docs/billing/invoices/subscription#adding-upcoming-invoice-items">Subscription Invoices</a>.
 *
 * @property string $id Unique identifier for the object.
 * @property string $object String representing the object's type. Objects of the same type share the same value.
 * @property int $amount Amount (in the <code>currency</code> specified) of the invoice item. This should always be equal to <code>unit_amount * quantity</code>.
 * @property string $currency Three-letter <a href="https://www.iso.org/iso-4217-currency-codes.html">ISO currency code</a>, in lowercase. Must be a <a href="https://stripe.com/docs/currencies">supported currency</a>.
 * @property string|\Stripe\Customer $customer The ID of the customer who will be billed when this invoice item is billed.
 * @property int $date Time at which the object was created. Measured in seconds since the Unix epoch.
 * @property null|string $description An arbitrary string attached to the object. Often useful for displaying to users.
 * @property bool $discountable If true, discounts will apply to this invoice item. Always false for prorations.
 * @property null|(string|\Stripe\Discount)[] $discounts The discounts which apply to the invoice item. Item discounts are applied before invoice discounts. Use <code>expand[]=discounts</code> to expand each discount.
 * @property null|string|\Stripe\Invoice $invoice The ID of the invoice this invoice item belongs to.
 * @property bool $livemode Has the value <code>true</code> if the object exists in live mode or the value <code>false</code> if the object exists in test mode.
 * @property null|\Stripe\StripeObject $metadata Set of <a href="https://stripe.com/docs/api/metadata">key-value pairs</a> that you can attach to an object. This can be useful for storing additional information about the object in a structured format.
 * @property \Stripe\StripeObject $period
 * @property null|\Stripe\Plan $plan If the invoice item is a proration, the plan of the subscription that the proration was computed for.
 * @property null|\Stripe\Price $price The price of the invoice item.
 * @property bool $proration Whether the invoice item was created automatically as a proration adjustment when the customer switched plans.
 * @property int $quantity Quantity of units for the invoice item. If the invoice item is a proration, the quantity of the subscription that the proration was computed for.
 * @property null|string|\Stripe\Subscription $subscription The subscription that this invoice item has been created for, if any.
 * @property null|string $subscription_item The subscription item that this invoice item has been created for, if any.
 * @property null|\Stripe\TaxRate[] $tax_rates The tax rates which apply to the invoice item. When set, the <code>default_tax_rates</code> on the invoice do not apply to this invoice item.
 * @property null|string|\Stripe\TestHelpers\TestClock $test_clock ID of the test clock this invoice item belongs to.
 * @property null|int $unit_amount Unit amount (in the <code>currency</code> specified) of the invoice item.
 * @property null|string $unit_amount_decimal Same as <code>unit_amount</code>, but contains a decimal value with at most 12 decimal places.
 * @package report_adeptus_insights
 */
class InvoiceItem extends ApiResource
{
    const OBJECT_NAME = 'invoiceitem';

    use ApiOperations\Update;

    /**
     * Creates an item to be added to a draft invoice (up to 250 items per invoice). If
     * no invoice is specified, the item will be on the next invoice created for the
     * customer specified.
     *
     * @param null|array $params
     * @param null|array|string $options
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\InvoiceItem the created resource
     */
    public static function create($params = null, $options = null) {
        self::_validateParams($params);
        $url = static::classUrl();

        [$response, $opts] = static::_staticRequest('post', $url, $params, $options);
        $obj = \Stripe\Util\Util::convertToStripeObject($response->json, $opts);
        $obj->setLastResponse($response);

        return $obj;
    }

    /**
     * Deletes an invoice item, removing it from an invoice. Deleting invoice items is
     * only possible when they’re not attached to invoices, or if it’s attached to a
     * draft invoice.
     *
     * @param null|array $params
     * @param null|array|string $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\InvoiceItem the deleted resource
     */
    public function delete($params = null, $opts = null) {
        self::_validateParams($params);

        $url = $this->instanceUrl();
        [$response, $opts] = $this->_request('delete', $url, $params, $opts);
        $this->refreshFrom($response, $opts);

        return $this;
    }

    /**
     * Returns a list of your invoice items. Invoice items are returned sorted by
     * creation date, with the most recently created invoice items appearing first.
     *
     * @param null|array $params
     * @param null|array|string $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\Collection<\Stripe\InvoiceItem> of ApiResources
     */
    public static function all($params = null, $opts = null) {
        $url = static::classUrl();

        return static::_requestPage($url, \Stripe\Collection::class, $params, $opts);
    }

    /**
     * Retrieves the invoice item with the given ID.
     *
     * @param array|string $id the ID of the API resource to retrieve, or an options array containing an `id` key
     * @param null|array|string $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\InvoiceItem
     */
    public static function retrieve($id, $opts = null) {
        $opts = \Stripe\Util\RequestOptions::parse($opts);
        $instance = new static($id, $opts);
        $instance->refresh();

        return $instance;
    }

    /**
     * Updates the amount or description of an invoice item on an upcoming invoice.
     * Updating an invoice item is only possible before the invoice it’s attached to is
     * closed.
     *
     * @param string $id the ID of the resource to update
     * @param null|array $params
     * @param null|array|string $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\InvoiceItem the updated resource
     */
    public static function update($id, $params = null, $opts = null) {
        self::_validateParams($params);
        $url = static::resourceUrl($id);

        [$response, $opts] = static::_staticRequest('post', $url, $params, $opts);
        $obj = \Stripe\Util\Util::convertToStripeObject($response->json, $opts);
        $obj->setLastResponse($response);

        return $obj;
    }
}
