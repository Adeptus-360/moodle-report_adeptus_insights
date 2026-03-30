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
 * Shipping rates describe the price of shipping presented to your customers and
 * applied to a purchase. For more information, see <a href="https://stripe.com/docs/payments/during-payment/charge-shipping">Charge for shipping</a>.
 *
 * @property string $id Unique identifier for the object.
 * @property string $object String representing the object's type. Objects of the same type share the same value.
 * @property bool $active Whether the shipping rate can be used for new purchases. Defaults to <code>true</code>.
 * @property int $created Time at which the object was created. Measured in seconds since the Unix epoch.
 * @property null|\Stripe\StripeObject $delivery_estimate The estimated range for how long shipping will take, meant to be displayable to the customer. This will appear on CheckoutSessions.
 * @property null|string $display_name The name of the shipping rate, meant to be displayable to the customer. This will appear on CheckoutSessions.
 * @property null|\Stripe\StripeObject $fixed_amount
 * @property bool $livemode Has the value <code>true</code> if the object exists in live mode or the value <code>false</code> if the object exists in test mode.
 * @property \Stripe\StripeObject $metadata Set of <a href="https://stripe.com/docs/api/metadata">key-value pairs</a> that you can attach to an object. This can be useful for storing additional information about the object in a structured format.
 * @property null|string $tax_behavior Specifies whether the rate is considered inclusive of taxes or exclusive of taxes. One of <code>inclusive</code>, <code>exclusive</code>, or <code>unspecified</code>.
 * @property null|string|\Stripe\TaxCode $tax_code A <a href="https://stripe.com/docs/tax/tax-categories">tax code</a> ID. The Shipping tax code is <code>txcd_92010001</code>.
 * @property string $type The type of calculation to use on the shipping rate.
 * @package report_adeptus_insights
 */
class ShippingRate extends ApiResource
{
    const OBJECT_NAME = 'shipping_rate';

    use ApiOperations\Update;

    const TAX_BEHAVIOR_EXCLUSIVE = 'exclusive';
    const TAX_BEHAVIOR_INCLUSIVE = 'inclusive';
    const TAX_BEHAVIOR_UNSPECIFIED = 'unspecified';

    const TYPE_FIXED_AMOUNT = 'fixed_amount';

    /**
     * Creates a new shipping rate object.
     *
     * @param null|array $params
     * @param null|array|string $options
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\ShippingRate the created resource
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
     * Returns a list of your shipping rates.
     *
     * @param null|array $params
     * @param null|array|string $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\Collection<\Stripe\ShippingRate> of ApiResources
     */
    public static function all($params = null, $opts = null) {
        $url = static::classUrl();

        return static::_requestPage($url, \Stripe\Collection::class, $params, $opts);
    }

    /**
     * Returns the shipping rate object with the given ID.
     *
     * @param array|string $id the ID of the API resource to retrieve, or an options array containing an `id` key
     * @param null|array|string $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\ShippingRate
     */
    public static function retrieve($id, $opts = null) {
        $opts = \Stripe\Util\RequestOptions::parse($opts);
        $instance = new static($id, $opts);
        $instance->refresh();

        return $instance;
    }

    /**
     * Updates an existing shipping rate object.
     *
     * @param string $id the ID of the resource to update
     * @param null|array $params
     * @param null|array|string $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\ShippingRate the updated resource
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
