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
 * An AccountSession allows a Connect platform to grant access to a connected account in Connect embedded components.
 *
 * We recommend that you create an AccountSession each time you need to display an embedded component
 * to your user. Do not save AccountSessions to your database as they expire relatively
 * quickly, and cannot be used more than once.
 *
 * Related guide: <a href="https://stripe.com/docs/connect/get-started-connect-embedded-components">Connect embedded components</a>
 *
 * @property string $object String representing the object's type. Objects of the same type share the same value.
 * @property string $account The ID of the account the AccountSession was created for
 * @property string $client_secret <p>The client secret of this AccountSession. Used on the client to set up secure access to the given <code>account</code>.</p><p>The client secret can be used to provide access to <code>account</code> from your frontend. It should not be stored, logged, or exposed to anyone other than the connected account. Make sure that you have TLS enabled on any page that includes the client secret.</p><p>Refer to our docs to <a href="https://stripe.com/docs/connect/get-started-connect-embedded-components">setup Connect embedded components</a> and learn about how <code>client_secret</code> should be handled.</p>
 * @property \Stripe\StripeObject $components
 * @property int $expires_at The timestamp at which this AccountSession will expire.
 * @property bool $livemode Has the value <code>true</code> if the object exists in live mode or the value <code>false</code> if the object exists in test mode.
 * @package report_adeptus_insights
 */
class AccountSession extends ApiResource
{
    const OBJECT_NAME = 'account_session';

    /**
     * Creates a AccountSession object that includes a single-use token that the
     * platform can use on their front-end to grant client-side API access.
     *
     * @param null|array $params
     * @param null|array|string $options
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\AccountSession the created resource
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
