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

namespace Stripe\Service\Checkout;

/**
 * @phpstan-import-type RequestOptionsArray from \Stripe\Util\RequestOptions
 * @psalm-import-type RequestOptionsArray from \Stripe\Util\RequestOptions
 * @package report_adeptus_insights
 */
class SessionService extends \Stripe\Service\AbstractService
{
    /**
     * Returns a list of Checkout Sessions.
     *
     * @param null|array $params
     * @param null|RequestOptionsArray|\Stripe\Util\RequestOptions $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\Collection<\Stripe\Checkout\Session>
     */
    public function all($params = null, $opts = null) {
        return $this->requestCollection('get', '/v1/checkout/sessions', $params, $opts);
    }

    /**
     * When retrieving a Checkout Session, there is an includable
     * <strong>line_items</strong> property containing the first handful of those
     * items. There is also a URL where you can retrieve the full (paginated) list of
     * line items.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\Stripe\Util\RequestOptions $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\Collection<\Stripe\LineItem>
     */
    public function allLineItems($id, $params = null, $opts = null) {
        return $this->requestCollection('get', $this->buildPath('/v1/checkout/sessions/%s/line_items', $id), $params, $opts);
    }

    /**
     * Creates a Session object.
     *
     * @param null|array $params
     * @param null|RequestOptionsArray|\Stripe\Util\RequestOptions $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\Checkout\Session
     */
    public function create($params = null, $opts = null) {
        return $this->request('post', '/v1/checkout/sessions', $params, $opts);
    }

    /**
     * A Session can be expired when it is in one of these statuses: <code>open</code>.
     *
     * After it expires, a customer can’t complete a Session and customers loading the
     * Session see a message saying the Session is expired.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\Stripe\Util\RequestOptions $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\Checkout\Session
     */
    public function expire($id, $params = null, $opts = null) {
        return $this->request('post', $this->buildPath('/v1/checkout/sessions/%s/expire', $id), $params, $opts);
    }

    /**
     * Retrieves a Session object.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\Stripe\Util\RequestOptions $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\Checkout\Session
     */
    public function retrieve($id, $params = null, $opts = null) {
        return $this->request('get', $this->buildPath('/v1/checkout/sessions/%s', $id), $params, $opts);
    }

    /**
     * Updates a Session object.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\Stripe\Util\RequestOptions $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\Checkout\Session
     */
    public function update($id, $params = null, $opts = null) {
        return $this->request('post', $this->buildPath('/v1/checkout/sessions/%s', $id), $params, $opts);
    }
}
