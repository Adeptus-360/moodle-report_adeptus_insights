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
class MeterService extends \Stripe\Service\AbstractService
{
    /**
     * Retrieve a list of billing meters.
     *
     * @param null|array $params
     * @param null|RequestOptionsArray|\Stripe\Util\RequestOptions $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\Collection<\Stripe\Billing\Meter>
     */
    public function all($params = null, $opts = null) {
        return $this->requestCollection('get', '/v1/billing/meters', $params, $opts);
    }

    /**
     * Retrieve a list of billing meter event summaries.
     *
     * @param string $parentId
     * @param null|array $params
     * @param null|RequestOptionsArray|\Stripe\Util\RequestOptions $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\Collection<\Stripe\Billing\MeterEventSummary>
     */
    public function allEventSummaries($parentId, $params = null, $opts = null) {
        return $this->requestCollection('get', $this->buildPath('/v1/billing/meters/%s/event_summaries', $parentId), $params, $opts);
    }

    /**
     * Creates a billing meter.
     *
     * @param null|array $params
     * @param null|RequestOptionsArray|\Stripe\Util\RequestOptions $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\Billing\Meter
     */
    public function create($params = null, $opts = null) {
        return $this->request('post', '/v1/billing/meters', $params, $opts);
    }

    /**
     * Deactivates a billing meter.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\Stripe\Util\RequestOptions $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\Billing\Meter
     */
    public function deactivate($id, $params = null, $opts = null) {
        return $this->request('post', $this->buildPath('/v1/billing/meters/%s/deactivate', $id), $params, $opts);
    }

    /**
     * Reactivates a billing meter.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\Stripe\Util\RequestOptions $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\Billing\Meter
     */
    public function reactivate($id, $params = null, $opts = null) {
        return $this->request('post', $this->buildPath('/v1/billing/meters/%s/reactivate', $id), $params, $opts);
    }

    /**
     * Retrieves a billing meter given an ID.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\Stripe\Util\RequestOptions $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\Billing\Meter
     */
    public function retrieve($id, $params = null, $opts = null) {
        return $this->request('get', $this->buildPath('/v1/billing/meters/%s', $id), $params, $opts);
    }

    /**
     * Updates a billing meter.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\Stripe\Util\RequestOptions $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\Billing\Meter
     */
    public function update($id, $params = null, $opts = null) {
        return $this->request('post', $this->buildPath('/v1/billing/meters/%s', $id), $params, $opts);
    }
}
