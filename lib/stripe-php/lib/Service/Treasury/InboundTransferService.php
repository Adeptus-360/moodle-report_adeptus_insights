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

namespace Stripe\Service\Treasury;

/**
 * @phpstan-import-type RequestOptionsArray from \Stripe\Util\RequestOptions
 * @psalm-import-type RequestOptionsArray from \Stripe\Util\RequestOptions
 * @package report_adeptus_insights
 */
class InboundTransferService extends \Stripe\Service\AbstractService
{
    /**
     * Returns a list of InboundTransfers sent from the specified FinancialAccount.
     *
     * @param null|array $params
     * @param null|RequestOptionsArray|\Stripe\Util\RequestOptions $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\Collection<\Stripe\Treasury\InboundTransfer>
     */
    public function all($params = null, $opts = null) {
        return $this->requestCollection('get', '/v1/treasury/inbound_transfers', $params, $opts);
    }

    /**
     * Cancels an InboundTransfer.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\Stripe\Util\RequestOptions $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\Treasury\InboundTransfer
     */
    public function cancel($id, $params = null, $opts = null) {
        return $this->request('post', $this->buildPath('/v1/treasury/inbound_transfers/%s/cancel', $id), $params, $opts);
    }

    /**
     * Creates an InboundTransfer.
     *
     * @param null|array $params
     * @param null|RequestOptionsArray|\Stripe\Util\RequestOptions $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\Treasury\InboundTransfer
     */
    public function create($params = null, $opts = null) {
        return $this->request('post', '/v1/treasury/inbound_transfers', $params, $opts);
    }

    /**
     * Retrieves the details of an existing InboundTransfer.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\Stripe\Util\RequestOptions $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\Treasury\InboundTransfer
     */
    public function retrieve($id, $params = null, $opts = null) {
        return $this->request('get', $this->buildPath('/v1/treasury/inbound_transfers/%s', $id), $params, $opts);
    }
}
