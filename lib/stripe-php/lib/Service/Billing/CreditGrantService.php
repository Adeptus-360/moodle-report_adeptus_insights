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

namespace Stripe\Service\Billing;

/**
 * @phpstan-import-type RequestOptionsArray from \Stripe\Util\RequestOptions
 * @psalm-import-type RequestOptionsArray from \Stripe\Util\RequestOptions
 * @package report_adeptus_insights
 */
class CreditGrantService extends \Stripe\Service\AbstractService
{
    /**
     * Retrieve a list of credit grants.
     *
     * @param null|array $params
     * @param null|RequestOptionsArray|\Stripe\Util\RequestOptions $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\Collection<\Stripe\Billing\CreditGrant>
     */
    public function all($params = null, $opts = null) {
        return $this->requestCollection('get', '/v1/billing/credit_grants', $params, $opts);
    }

    /**
     * Creates a credit grant.
     *
     * @param null|array $params
     * @param null|RequestOptionsArray|\Stripe\Util\RequestOptions $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\Billing\CreditGrant
     */
    public function create($params = null, $opts = null) {
        return $this->request('post', '/v1/billing/credit_grants', $params, $opts);
    }

    /**
     * Expires a credit grant.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\Stripe\Util\RequestOptions $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\Billing\CreditGrant
     */
    public function expire($id, $params = null, $opts = null) {
        return $this->request('post', $this->buildPath('/v1/billing/credit_grants/%s/expire', $id), $params, $opts);
    }

    /**
     * Retrieves a credit grant.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\Stripe\Util\RequestOptions $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\Billing\CreditGrant
     */
    public function retrieve($id, $params = null, $opts = null) {
        return $this->request('get', $this->buildPath('/v1/billing/credit_grants/%s', $id), $params, $opts);
    }

    /**
     * Updates a credit grant.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\Stripe\Util\RequestOptions $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\Billing\CreditGrant
     */
    public function update($id, $params = null, $opts = null) {
        return $this->request('post', $this->buildPath('/v1/billing/credit_grants/%s', $id), $params, $opts);
    }

    /**
     * Voids a credit grant.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\Stripe\Util\RequestOptions $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\Billing\CreditGrant
     */
    public function voidGrant($id, $params = null, $opts = null) {
        return $this->request('post', $this->buildPath('/v1/billing/credit_grants/%s/void', $id), $params, $opts);
    }
}
