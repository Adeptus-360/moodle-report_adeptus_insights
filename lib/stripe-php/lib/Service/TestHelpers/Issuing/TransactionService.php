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

namespace Stripe\Service\TestHelpers\Issuing;

/**
 * @phpstan-import-type RequestOptionsArray from \Stripe\Util\RequestOptions
 * @psalm-import-type RequestOptionsArray from \Stripe\Util\RequestOptions
 * @package report_adeptus_insights
 */
class TransactionService extends \Stripe\Service\AbstractService
{
    /**
     * Allows the user to capture an arbitrary amount, also known as a forced capture.
     *
     * @param null|array $params
     * @param null|RequestOptionsArray|\Stripe\Util\RequestOptions $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\Issuing\Transaction
     */
    public function createForceCapture($params = null, $opts = null) {
        return $this->request('post', '/v1/test_helpers/issuing/transactions/create_force_capture', $params, $opts);
    }

    /**
     * Allows the user to refund an arbitrary amount, also known as a unlinked refund.
     *
     * @param null|array $params
     * @param null|RequestOptionsArray|\Stripe\Util\RequestOptions $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\Issuing\Transaction
     */
    public function createUnlinkedRefund($params = null, $opts = null) {
        return $this->request('post', '/v1/test_helpers/issuing/transactions/create_unlinked_refund', $params, $opts);
    }

    /**
     * Refund a test-mode Transaction.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\Stripe\Util\RequestOptions $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\Issuing\Transaction
     */
    public function refund($id, $params = null, $opts = null) {
        return $this->request('post', $this->buildPath('/v1/test_helpers/issuing/transactions/%s/refund', $id), $params, $opts);
    }
}
