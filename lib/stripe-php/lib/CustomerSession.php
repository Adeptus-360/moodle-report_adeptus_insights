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
 * A Customer Session allows you to grant Stripe's frontend SDKs (like Stripe.js) client-side access
 * control over a Customer.
 *
 * Related guides: <a href="/payments/accept-a-payment-deferred?platform=web&amp;type=payment#save-payment-methods">Customer Session with the Payment Element</a>,
 * <a href="/payments/checkout/pricing-table#customer-session">Customer Session with the Pricing Table</a>,
 * <a href="/payment-links/buy-button#pass-an-existing-customer">Customer Session with the Buy Button</a>.
 *
 * @property string $object String representing the object's type. Objects of the same type share the same value.
 * @property string $client_secret <p>The client secret of this Customer Session. Used on the client to set up secure access to the given <code>customer</code>.</p><p>The client secret can be used to provide access to <code>customer</code> from your frontend. It should not be stored, logged, or exposed to anyone other than the relevant customer. Make sure that you have TLS enabled on any page that includes the client secret.</p>
 * @property null|\Stripe\StripeObject $components Configuration for the components supported by this Customer Session.
 * @property int $created Time at which the object was created. Measured in seconds since the Unix epoch.
 * @property string|\Stripe\Customer $customer The Customer the Customer Session was created for.
 * @property int $expires_at The timestamp at which this Customer Session will expire.
 * @property bool $livemode Has the value <code>true</code> if the object exists in live mode or the value <code>false</code> if the object exists in test mode.
 * @package report_adeptus_insights
 */
class CustomerSession extends ApiResource
{
    const OBJECT_NAME = 'customer_session';

    /**
     * Creates a Customer Session object that includes a single-use client secret that
     * you can use on your front-end to grant client-side API access for certain
     * customer resources.
     *
     * @param null|array $params
     * @param null|array|string $options
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\CustomerSession the created resource
     */
    public static function create($params = null, $options = null) {
        self::_validateParams($params);
        $url = static::classUrl();

        [$response, $opts] = static::_staticRequest('post', $url, $params, $options);
        $obj = \Stripe\Util\Util::convertToStripeObject($response->json, $opts);
        $obj->setLastResponse($response);

        return $obj;
    }
}
