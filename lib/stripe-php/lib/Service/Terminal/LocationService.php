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

namespace Stripe\Service\Terminal;

/**
 * @phpstan-import-type RequestOptionsArray from \Stripe\Util\RequestOptions
 * @psalm-import-type RequestOptionsArray from \Stripe\Util\RequestOptions
 * @package report_adeptus_insights
 */
class LocationService extends \Stripe\Service\AbstractService
{
    /**
     * Returns a list of <code>Location</code> objects.
     *
     * @param null|array $params
     * @param null|RequestOptionsArray|\Stripe\Util\RequestOptions $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\Collection<\Stripe\Terminal\Location>
     */
    public function all($params = null, $opts = null) {
        return $this->requestCollection('get', '/v1/terminal/locations', $params, $opts);
    }

    /**
     * Creates a new <code>Location</code> object. For further details, including which
     * address fields are required in each country, see the <a
     * href="/docs/terminal/fleet/locations">Manage locations</a> guide.
     *
     * @param null|array $params
     * @param null|RequestOptionsArray|\Stripe\Util\RequestOptions $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\Terminal\Location
     */
    public function create($params = null, $opts = null) {
        return $this->request('post', '/v1/terminal/locations', $params, $opts);
    }

    /**
     * Deletes a <code>Location</code> object.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\Stripe\Util\RequestOptions $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\Terminal\Location
     */
    public function delete($id, $params = null, $opts = null) {
        return $this->request('delete', $this->buildPath('/v1/terminal/locations/%s', $id), $params, $opts);
    }

    /**
     * Retrieves a <code>Location</code> object.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\Stripe\Util\RequestOptions $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\Terminal\Location
     */
    public function retrieve($id, $params = null, $opts = null) {
        return $this->request('get', $this->buildPath('/v1/terminal/locations/%s', $id), $params, $opts);
    }

    /**
     * Updates a <code>Location</code> object by setting the values of the parameters
     * passed. Any parameters not provided will be left unchanged.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\Stripe\Util\RequestOptions $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\Terminal\Location
     */
    public function update($id, $params = null, $opts = null) {
        return $this->request('post', $this->buildPath('/v1/terminal/locations/%s', $id), $params, $opts);
    }
}
