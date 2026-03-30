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

namespace Stripe\Service;

/**
 * @phpstan-import-type RequestOptionsArray from \Stripe\Util\RequestOptions
 * @psalm-import-type RequestOptionsArray from \Stripe\Util\RequestOptions
 * @package report_adeptus_insights
 */
class SourceService extends \Stripe\Service\AbstractService
{
    /**
     * List source transactions for a given source.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\Stripe\Util\RequestOptions $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\Collection<\Stripe\SourceTransaction>
     */
    public function allSourceTransactions($id, $params = null, $opts = null) {
        return $this->requestCollection('get', $this->buildPath('/v1/sources/%s/source_transactions', $id), $params, $opts);
    }

    /**
     * Creates a new source object.
     *
     * @param null|array $params
     * @param null|RequestOptionsArray|\Stripe\Util\RequestOptions $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\Source
     */
    public function create($params = null, $opts = null) {
        return $this->request('post', '/v1/sources', $params, $opts);
    }

    /**
     * Delete a specified source for a given customer.
     *
     * @param string $parentId
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\Stripe\Util\RequestOptions $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\Account|\Stripe\BankAccount|\Stripe\Card|\Stripe\Source
     */
    public function detach($parentId, $id, $params = null, $opts = null) {
        return $this->request('delete', $this->buildPath('/v1/customers/%s/sources/%s', $parentId, $id), $params, $opts);
    }

    /**
     * Retrieves an existing source object. Supply the unique source ID from a source
     * creation request and Stripe will return the corresponding up-to-date source
     * object information.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\Stripe\Util\RequestOptions $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\Source
     */
    public function retrieve($id, $params = null, $opts = null) {
        return $this->request('get', $this->buildPath('/v1/sources/%s', $id), $params, $opts);
    }

    /**
     * Updates the specified source by setting the values of the parameters passed. Any
     * parameters not provided will be left unchanged.
     *
     * This request accepts the <code>metadata</code> and <code>owner</code> as
     * arguments. It is also possible to update type specific information for selected
     * payment methods. Please refer to our <a href="/docs/sources">payment method
     * guides</a> for more detail.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\Stripe\Util\RequestOptions $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\Source
     */
    public function update($id, $params = null, $opts = null) {
        return $this->request('post', $this->buildPath('/v1/sources/%s', $id), $params, $opts);
    }

    /**
     * Verify a given source.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\Stripe\Util\RequestOptions $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\Source
     */
    public function verify($id, $params = null, $opts = null) {
        return $this->request('post', $this->buildPath('/v1/sources/%s/verify', $id), $params, $opts);
    }
}
