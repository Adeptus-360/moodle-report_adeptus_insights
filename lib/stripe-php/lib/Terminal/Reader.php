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

namespace Stripe\Terminal;

/**
 * A Reader represents a physical device for accepting payment details.
 *
 * Related guide: <a href="https://stripe.com/docs/terminal/payments/connect-reader">Connecting to a reader</a>
 *
 * @property string $id Unique identifier for the object.
 * @property string $object String representing the object's type. Objects of the same type share the same value.
 * @property null|\Stripe\StripeObject $action The most recent action performed by the reader.
 * @property null|string $device_sw_version The current software version of the reader.
 * @property string $device_type Type of reader, one of <code>bbpos_wisepad3</code>, <code>stripe_m2</code>, <code>stripe_s700</code>, <code>bbpos_chipper2x</code>, <code>bbpos_wisepos_e</code>, <code>verifone_P400</code>, <code>simulated_wisepos_e</code>, or <code>mobile_phone_reader</code>.
 * @property null|string $ip_address The local IP address of the reader.
 * @property string $label Custom label given to the reader for easier identification.
 * @property bool $livemode Has the value <code>true</code> if the object exists in live mode or the value <code>false</code> if the object exists in test mode.
 * @property null|string|\Stripe\Terminal\Location $location The location identifier of the reader.
 * @property \Stripe\StripeObject $metadata Set of <a href="https://stripe.com/docs/api/metadata">key-value pairs</a> that you can attach to an object. This can be useful for storing additional information about the object in a structured format.
 * @property string $serial_number Serial number of the reader.
 * @property null|string $status The networking status of the reader. We do not recommend using this field in flows that may block taking payments.
 * @package report_adeptus_insights
 */
class Reader extends \Stripe\ApiResource
{
    const OBJECT_NAME = 'terminal.reader';

    use \Stripe\ApiOperations\Update;

    const DEVICE_TYPE_BBPOS_CHIPPER2X = 'bbpos_chipper2x';
    const DEVICE_TYPE_BBPOS_WISEPAD3 = 'bbpos_wisepad3';
    const DEVICE_TYPE_BBPOS_WISEPOS_E = 'bbpos_wisepos_e';
    const DEVICE_TYPE_MOBILE_PHONE_READER = 'mobile_phone_reader';
    const DEVICE_TYPE_SIMULATED_WISEPOS_E = 'simulated_wisepos_e';
    const DEVICE_TYPE_STRIPE_M2 = 'stripe_m2';
    const DEVICE_TYPE_STRIPE_S700 = 'stripe_s700';
    const DEVICE_TYPE_VERIFONE_P400 = 'verifone_P400';

    const STATUS_OFFLINE = 'offline';
    const STATUS_ONLINE = 'online';

    /**
     * Creates a new <code>Reader</code> object.
     *
     * @param null|array $params
     * @param null|array|string $options
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\Terminal\Reader the created resource
     */
    public static function create($params = null, $options = null) {
        self::_validateParams($params);
        $url = static::classUrl();

        [$response, $opts] = static::_staticRequest('post', $url, $params, $options);
        $obj = \Stripe\Util\Util::convertToStripeObject($response->json, $opts);
        $obj->setLastResponse($response);

        return $obj;
    }

    /**
     * Deletes a <code>Reader</code> object.
     *
     * @param null|array $params
     * @param null|array|string $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\Terminal\Reader the deleted resource
     */
    public function delete($params = null, $opts = null) {
        self::_validateParams($params);

        $url = $this->instanceUrl();
        [$response, $opts] = $this->_request('delete', $url, $params, $opts);
        $this->refreshFrom($response, $opts);

        return $this;
    }

    /**
     * Returns a list of <code>Reader</code> objects.
     *
     * @param null|array $params
     * @param null|array|string $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\Collection<\Stripe\Terminal\Reader> of ApiResources
     */
    public static function all($params = null, $opts = null) {
        $url = static::classUrl();

        return static::_requestPage($url, \Stripe\Collection::class, $params, $opts);
    }

    /**
     * Retrieves a <code>Reader</code> object.
     *
     * @param array|string $id the ID of the API resource to retrieve, or an options array containing an `id` key
     * @param null|array|string $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\Terminal\Reader
     */
    public static function retrieve($id, $opts = null) {
        $opts = \Stripe\Util\RequestOptions::parse($opts);
        $instance = new static($id, $opts);
        $instance->refresh();

        return $instance;
    }

    /**
     * Updates a <code>Reader</code> object by setting the values of the parameters
     * passed. Any parameters not provided will be left unchanged.
     *
     * @param string $id the ID of the resource to update
     * @param null|array $params
     * @param null|array|string $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\Terminal\Reader the updated resource
     */
    public static function update($id, $params = null, $opts = null) {
        self::_validateParams($params);
        $url = static::resourceUrl($id);

        [$response, $opts] = static::_staticRequest('post', $url, $params, $opts);
        $obj = \Stripe\Util\Util::convertToStripeObject($response->json, $opts);
        $obj->setLastResponse($response);

        return $obj;
    }

    /**
     * @param null|array $params
     * @param null|array|string $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\Terminal\Reader the canceled reader
     */
    public function cancelAction($params = null, $opts = null) {
        $url = $this->instanceUrl() . '/cancel_action';
        [$response, $opts] = $this->_request('post', $url, $params, $opts);
        $this->refreshFrom($response, $opts);

        return $this;
    }

    /**
     * @param null|array $params
     * @param null|array|string $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\Terminal\Reader the processed reader
     */
    public function processPaymentIntent($params = null, $opts = null) {
        $url = $this->instanceUrl() . '/process_payment_intent';
        [$response, $opts] = $this->_request('post', $url, $params, $opts);
        $this->refreshFrom($response, $opts);

        return $this;
    }

    /**
     * @param null|array $params
     * @param null|array|string $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\Terminal\Reader the processed reader
     */
    public function processSetupIntent($params = null, $opts = null) {
        $url = $this->instanceUrl() . '/process_setup_intent';
        [$response, $opts] = $this->_request('post', $url, $params, $opts);
        $this->refreshFrom($response, $opts);

        return $this;
    }

    /**
     * @param null|array $params
     * @param null|array|string $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\Terminal\Reader the refunded reader
     */
    public function refundPayment($params = null, $opts = null) {
        $url = $this->instanceUrl() . '/refund_payment';
        [$response, $opts] = $this->_request('post', $url, $params, $opts);
        $this->refreshFrom($response, $opts);

        return $this;
    }

    /**
     * @param null|array $params
     * @param null|array|string $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\Terminal\Reader the seted reader
     */
    public function setReaderDisplay($params = null, $opts = null) {
        $url = $this->instanceUrl() . '/set_reader_display';
        [$response, $opts] = $this->_request('post', $url, $params, $opts);
        $this->refreshFrom($response, $opts);

        return $this;
    }
}
