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
class CouponService extends \Stripe\Service\AbstractService
{
    /**
     * Returns a list of your coupons.
     *
     * @param null|array $params
     * @param null|RequestOptionsArray|\Stripe\Util\RequestOptions $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\Collection<\Stripe\Coupon>
     */
    public function all($params = null, $opts = null) {
        return $this->requestCollection('get', '/v1/coupons', $params, $opts);
    }

    /**
     * You can create coupons easily via the <a
     * href="https://dashboard.stripe.com/coupons">coupon management</a> page of the
     * Stripe dashboard. Coupon creation is also accessible via the API if you need to
     * create coupons on the fly.
     *
     * A coupon has either a <code>percent_off</code> or an <code>amount_off</code> and
     * <code>currency</code>. If you set an <code>amount_off</code>, that amount will
     * be subtracted from any invoice’s subtotal. For example, an invoice with a
     * subtotal of <currency>100</currency> will have a final total of
     * <currency>0</currency> if a coupon with an <code>amount_off</code> of
     * <amount>200</amount> is applied to it and an invoice with a subtotal of
     * <currency>300</currency> will have a final total of <currency>100</currency> if
     * a coupon with an <code>amount_off</code> of <amount>200</amount> is applied to
     * it.
     *
     * @param null|array $params
     * @param null|RequestOptionsArray|\Stripe\Util\RequestOptions $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\Coupon
     */
    public function create($params = null, $opts = null) {
        return $this->request('post', '/v1/coupons', $params, $opts);
    }

    /**
     * You can delete coupons via the <a
     * href="https://dashboard.stripe.com/coupons">coupon management</a> page of the
     * Stripe dashboard. However, deleting a coupon does not affect any customers who
     * have already applied the coupon; it means that new customers can’t redeem the
     * coupon. You can also delete coupons via the API.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\Stripe\Util\RequestOptions $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\Coupon
     */
    public function delete($id, $params = null, $opts = null) {
        return $this->request('delete', $this->buildPath('/v1/coupons/%s', $id), $params, $opts);
    }

    /**
     * Retrieves the coupon with the given ID.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\Stripe\Util\RequestOptions $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\Coupon
     */
    public function retrieve($id, $params = null, $opts = null) {
        return $this->request('get', $this->buildPath('/v1/coupons/%s', $id), $params, $opts);
    }

    /**
     * Updates the metadata of a coupon. Other coupon details (currency, duration,
     * amount_off) are, by design, not editable.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\Stripe\Util\RequestOptions $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\Coupon
     */
    public function update($id, $params = null, $opts = null) {
        return $this->request('post', $this->buildPath('/v1/coupons/%s', $id), $params, $opts);
    }
}
