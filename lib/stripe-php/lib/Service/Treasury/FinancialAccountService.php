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
class FinancialAccountService extends \Stripe\Service\AbstractService
{
    /**
     * Returns a list of FinancialAccounts.
     *
     * @param null|array $params
     * @param null|RequestOptionsArray|\Stripe\Util\RequestOptions $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\Collection<\Stripe\Treasury\FinancialAccount>
     */
    public function all($params = null, $opts = null) {
        return $this->requestCollection('get', '/v1/treasury/financial_accounts', $params, $opts);
    }

    /**
     * Creates a new FinancialAccount. For now, each connected account can only have
     * one FinancialAccount.
     *
     * @param null|array $params
     * @param null|RequestOptionsArray|\Stripe\Util\RequestOptions $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\Treasury\FinancialAccount
     */
    public function create($params = null, $opts = null) {
        return $this->request('post', '/v1/treasury/financial_accounts', $params, $opts);
    }

    /**
     * Retrieves the details of a FinancialAccount.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\Stripe\Util\RequestOptions $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\Treasury\FinancialAccount
     */
    public function retrieve($id, $params = null, $opts = null) {
        return $this->request('get', $this->buildPath('/v1/treasury/financial_accounts/%s', $id), $params, $opts);
    }

    /**
     * Retrieves Features information associated with the FinancialAccount.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\Stripe\Util\RequestOptions $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\Treasury\FinancialAccountFeatures
     */
    public function retrieveFeatures($id, $params = null, $opts = null) {
        return $this->request('get', $this->buildPath('/v1/treasury/financial_accounts/%s/features', $id), $params, $opts);
    }

    /**
     * Updates the details of a FinancialAccount.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\Stripe\Util\RequestOptions $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\Treasury\FinancialAccount
     */
    public function update($id, $params = null, $opts = null) {
        return $this->request('post', $this->buildPath('/v1/treasury/financial_accounts/%s', $id), $params, $opts);
    }

    /**
     * Updates the Features associated with a FinancialAccount.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\Stripe\Util\RequestOptions $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\Treasury\FinancialAccountFeatures
     */
    public function updateFeatures($id, $params = null, $opts = null) {
        return $this->request('post', $this->buildPath('/v1/treasury/financial_accounts/%s/features', $id), $params, $opts);
    }
}
