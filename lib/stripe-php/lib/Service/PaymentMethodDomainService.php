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
class PaymentMethodDomainService extends \Stripe\Service\AbstractService
{
    /**
     * Lists the details of existing payment method domains.
     *
     * @param null|array $params
     * @param null|RequestOptionsArray|\Stripe\Util\RequestOptions $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\Collection<\Stripe\PaymentMethodDomain>
     */
    public function all($params = null, $opts = null) {
        return $this->requestCollection('get', '/v1/payment_method_domains', $params, $opts);
    }

    /**
     * Creates a payment method domain.
     *
     * @param null|array $params
     * @param null|RequestOptionsArray|\Stripe\Util\RequestOptions $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\PaymentMethodDomain
     */
    public function create($params = null, $opts = null) {
        return $this->request('post', '/v1/payment_method_domains', $params, $opts);
    }

    /**
     * Retrieves the details of an existing payment method domain.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\Stripe\Util\RequestOptions $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\PaymentMethodDomain
     */
    public function retrieve($id, $params = null, $opts = null) {
        return $this->request('get', $this->buildPath('/v1/payment_method_domains/%s', $id), $params, $opts);
    }

    /**
     * Updates an existing payment method domain.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\Stripe\Util\RequestOptions $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\PaymentMethodDomain
     */
    public function update($id, $params = null, $opts = null) {
        return $this->request('post', $this->buildPath('/v1/payment_method_domains/%s', $id), $params, $opts);
    }

    /**
     * Some payment methods such as Apple Pay require additional steps to verify a
     * domain. If the requirements weren’t satisfied when the domain was created, the
     * payment method will be inactive on the domain. The payment method doesn’t appear
     * in Elements for this domain until it is active.
     *
     * To activate a payment method on an existing payment method domain, complete the
     * required validation steps specific to the payment method, and then validate the
     * payment method domain with this endpoint.
     *
     * Related guides: <a
     * href="/docs/payments/payment-methods/pmd-registration">Payment method
     * domains</a>.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\Stripe\Util\RequestOptions $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\PaymentMethodDomain
     */
    public function validate($id, $params = null, $opts = null) {
        return $this->request('post', $this->buildPath('/v1/payment_method_domains/%s/validate', $id), $params, $opts);
    }
}
