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

namespace Stripe\Apps;

/**
 * Secret Store is an API that allows Stripe Apps developers to securely persist secrets for use by UI Extensions and app backends.
 *
 * The primary resource in Secret Store is a <code>secret</code>. Other apps can't view secrets created by an app. Additionally, secrets are scoped to provide further permission control.
 *
 * All Dashboard users and the app backend share <code>account</code> scoped secrets. Use the <code>account</code> scope for secrets that don't change per-user, like a third-party API key.
 *
 * A <code>user</code> scoped secret is accessible by the app backend and one specific Dashboard user. Use the <code>user</code> scope for per-user secrets like per-user OAuth tokens, where different users might have different permissions.
 *
 * Related guide: <a href="https://stripe.com/docs/stripe-apps/store-auth-data-custom-objects">Store data between page reloads</a>
 *
 * @property string $id Unique identifier for the object.
 * @property string $object String representing the object's type. Objects of the same type share the same value.
 * @property int $created Time at which the object was created. Measured in seconds since the Unix epoch.
 * @property null|bool $deleted If true, indicates that this secret has been deleted
 * @property null|int $expires_at The Unix timestamp for the expiry time of the secret, after which the secret deletes.
 * @property bool $livemode Has the value <code>true</code> if the object exists in live mode or the value <code>false</code> if the object exists in test mode.
 * @property string $name A name for the secret that's unique within the scope.
 * @property null|string $payload The plaintext secret value to be stored.
 * @property \Stripe\StripeObject $scope
 * @package report_adeptus_insights
 */
class Secret extends \Stripe\ApiResource
{
    const OBJECT_NAME = 'apps.secret';

    /**
     * Create or replace a secret in the secret store.
     *
     * @param null|array $params
     * @param null|array|string $options
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\Apps\Secret the created resource
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
     * List all secrets stored on the given scope.
     *
     * @param null|array $params
     * @param null|array|string $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\Collection<\Stripe\Apps\Secret> of ApiResources
     */
    public static function all($params = null, $opts = null) {
        $url = static::classUrl();

        return static::_requestPage($url, \Stripe\Collection::class, $params, $opts);
    }

    /**
     * @param null|array $params
     * @param null|array|string $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\Apps\Secret the deleted secret
     */
    public static function deleteWhere($params = null, $opts = null) {
        $url = static::classUrl() . '/delete';
        [$response, $opts] = static::_staticRequest('post', $url, $params, $opts);
        $obj = \Stripe\Util\Util::convertToStripeObject($response->json, $opts);
        $obj->setLastResponse($response);

        return $obj;
    }

    /**
     * @param null|array $params
     * @param null|array|string $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\Apps\Secret the finded secret
     */
    public static function find($params = null, $opts = null) {
        $url = static::classUrl() . '/find';
        [$response, $opts] = static::_staticRequest('get', $url, $params, $opts);
        $obj = \Stripe\Util\Util::convertToStripeObject($response->json, $opts);
        $obj->setLastResponse($response);

        return $obj;
    }
}
