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

namespace Stripe\Events;

/**
 * @property \Stripe\RelatedObject $related_object Object containing the reference to API resource relevant to the event
 * @property \Stripe\EventData\V1BillingMeterErrorReportTriggeredEventData $data data associated with the event
 * @package report_adeptus_insights
 */
class V1BillingMeterErrorReportTriggeredEvent extends \Stripe\V2\Event
{
    const LOOKUP_TYPE = 'v1.billing.meter.error_report_triggered';

    /**
     * Retrieves the related object from the API. Make an API request on every call.
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\Billing\Meter
     */
    public function fetchRelatedObject() {
        $apiMode = \Stripe\Util\Util::getApiMode($this->related_object->url);
        [$object, $options] = $this->_request(
            'get',
            $this->related_object->url,
            [],
            ['stripe_account' => $this->context],
            [],
            $apiMode
        );

        return \Stripe\Util\Util::convertToStripeObject($object, $options, $apiMode);
    }

    public static function constructFrom($values, $opts = null, $apiMode = 'v2') {
        $evt = parent::constructFrom($values, $opts, $apiMode);
        if (null !== $evt->data) {
            $evt->data = \Stripe\EventData\V1BillingMeterErrorReportTriggeredEventData::constructFrom($evt->data, $opts, $apiMode);
        }

        return $evt;
    }
}
