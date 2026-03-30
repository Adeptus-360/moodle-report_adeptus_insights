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
class SubscriptionScheduleService extends \Stripe\Service\AbstractService
{
    /**
     * Retrieves the list of your subscription schedules.
     *
     * @param null|array $params
     * @param null|RequestOptionsArray|\Stripe\Util\RequestOptions $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\Collection<\Stripe\SubscriptionSchedule>
     */
    public function all($params = null, $opts = null) {
        return $this->requestCollection('get', '/v1/subscription_schedules', $params, $opts);
    }

    /**
     * Cancels a subscription schedule and its associated subscription immediately (if
     * the subscription schedule has an active subscription). A subscription schedule
     * can only be canceled if its status is <code>not_started</code> or
     * <code>active</code>.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\Stripe\Util\RequestOptions $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\SubscriptionSchedule
     */
    public function cancel($id, $params = null, $opts = null) {
        return $this->request('post', $this->buildPath('/v1/subscription_schedules/%s/cancel', $id), $params, $opts);
    }

    /**
     * Creates a new subscription schedule object. Each customer can have up to 500
     * active or scheduled subscriptions.
     *
     * @param null|array $params
     * @param null|RequestOptionsArray|\Stripe\Util\RequestOptions $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\SubscriptionSchedule
     */
    public function create($params = null, $opts = null) {
        return $this->request('post', '/v1/subscription_schedules', $params, $opts);
    }

    /**
     * Releases the subscription schedule immediately, which will stop scheduling of
     * its phases, but leave any existing subscription in place. A schedule can only be
     * released if its status is <code>not_started</code> or <code>active</code>. If
     * the subscription schedule is currently associated with a subscription, releasing
     * it will remove its <code>subscription</code> property and set the subscription’s
     * ID to the <code>released_subscription</code> property.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\Stripe\Util\RequestOptions $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\SubscriptionSchedule
     */
    public function release($id, $params = null, $opts = null) {
        return $this->request('post', $this->buildPath('/v1/subscription_schedules/%s/release', $id), $params, $opts);
    }

    /**
     * Retrieves the details of an existing subscription schedule. You only need to
     * supply the unique subscription schedule identifier that was returned upon
     * subscription schedule creation.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\Stripe\Util\RequestOptions $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\SubscriptionSchedule
     */
    public function retrieve($id, $params = null, $opts = null) {
        return $this->request('get', $this->buildPath('/v1/subscription_schedules/%s', $id), $params, $opts);
    }

    /**
     * Updates an existing subscription schedule.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\Stripe\Util\RequestOptions $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\SubscriptionSchedule
     */
    public function update($id, $params = null, $opts = null) {
        return $this->request('post', $this->buildPath('/v1/subscription_schedules/%s', $id), $params, $opts);
    }
}
