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
class DisputeService extends \Stripe\Service\AbstractService
{
    /**
     * Returns a list of your disputes.
     *
     * @param null|array $params
     * @param null|RequestOptionsArray|\Stripe\Util\RequestOptions $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\Collection<\Stripe\Dispute>
     */
    public function all($params = null, $opts = null) {
        return $this->requestCollection('get', '/v1/disputes', $params, $opts);
    }

    /**
     * Closing the dispute for a charge indicates that you do not have any evidence to
     * submit and are essentially dismissing the dispute, acknowledging it as lost.
     *
     * The status of the dispute will change from <code>needs_response</code> to
     * <code>lost</code>. <em>Closing a dispute is irreversible</em>.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\Stripe\Util\RequestOptions $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\Dispute
     */
    public function close($id, $params = null, $opts = null) {
        return $this->request('post', $this->buildPath('/v1/disputes/%s/close', $id), $params, $opts);
    }

    /**
     * Retrieves the dispute with the given ID.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\Stripe\Util\RequestOptions $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\Dispute
     */
    public function retrieve($id, $params = null, $opts = null) {
        return $this->request('get', $this->buildPath('/v1/disputes/%s', $id), $params, $opts);
    }

    /**
     * When you get a dispute, contacting your customer is always the best first step.
     * If that doesn’t work, you can submit evidence to help us resolve the dispute in
     * your favor. You can do this in your <a
     * href="https://dashboard.stripe.com/disputes">dashboard</a>, but if you prefer,
     * you can use the API to submit evidence programmatically.
     *
     * Depending on your dispute type, different evidence fields will give you a better
     * chance of winning your dispute. To figure out which evidence fields to provide,
     * see our <a href="/docs/disputes/categories">guide to dispute types</a>.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\Stripe\Util\RequestOptions $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\Dispute
     */
    public function update($id, $params = null, $opts = null) {
        return $this->request('post', $this->buildPath('/v1/disputes/%s', $id), $params, $opts);
    }
}
