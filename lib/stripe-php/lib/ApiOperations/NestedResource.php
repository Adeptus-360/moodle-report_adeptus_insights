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

namespace Stripe\ApiOperations;

/**
 * Trait for resources that have nested resources.
 *
 * This trait should only be applied to classes that derive from StripeObject.
 * @package report_adeptus_insights
 */
trait NestedResource
{
    /**
     * @param 'delete'|'get'|'post' $method
     * @param string $url
     * @param null|array $params
     * @param null|array|string $options
     *
     * @return \Stripe\StripeObject
     */
    protected static function _nestedResourceOperation($method, $url, $params = null, $options = null) {
        self::_validateParams($params);

        [$response, $opts] = static::_staticRequest($method, $url, $params, $options);
        $obj = \Stripe\Util\Util::convertToStripeObject($response->json, $opts);
        $obj->setLastResponse($response);

        return $obj;
    }

    /**
     * @param string $id
     * @param string $nestedPath
     * @param null|string $nestedId
     *
     * @return string
     */
    protected static function _nestedResourceUrl($id, $nestedPath, $nestedId = null) {
        $url = static::resourceUrl($id) . $nestedPath;
        if (null !== $nestedId) {
            $url .= "/{$nestedId}";
        }

        return $url;
    }

    /**
     * @param string $id
     * @param string $nestedPath
     * @param null|array $params
     * @param null|array|string $options
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\StripeObject
     */
    protected static function _createNestedResource($id, $nestedPath, $params = null, $options = null) {
        $url = static::_nestedResourceUrl($id, $nestedPath);

        return self::_nestedResourceOperation('post', $url, $params, $options);
    }

    /**
     * @param string $id
     * @param string $nestedPath
     * @param null|string $nestedId
     * @param null|array $params
     * @param null|array|string $options
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\StripeObject
     */
    protected static function _retrieveNestedResource($id, $nestedPath, $nestedId, $params = null, $options = null) {
        $url = static::_nestedResourceUrl($id, $nestedPath, $nestedId);

        return self::_nestedResourceOperation('get', $url, $params, $options);
    }

    /**
     * @param string $id
     * @param string $nestedPath
     * @param null|string $nestedId
     * @param null|array $params
     * @param null|array|string $options
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\StripeObject
     */
    protected static function _updateNestedResource($id, $nestedPath, $nestedId, $params = null, $options = null) {
        $url = static::_nestedResourceUrl($id, $nestedPath, $nestedId);

        return self::_nestedResourceOperation('post', $url, $params, $options);
    }

    /**
     * @param string $id
     * @param string $nestedPath
     * @param null|string $nestedId
     * @param null|array $params
     * @param null|array|string $options
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\StripeObject
     */
    protected static function _deleteNestedResource($id, $nestedPath, $nestedId, $params = null, $options = null) {
        $url = static::_nestedResourceUrl($id, $nestedPath, $nestedId);

        return self::_nestedResourceOperation('delete', $url, $params, $options);
    }

    /**
     * @param string $id
     * @param string $nestedPath
     * @param null|array $params
     * @param null|array|string $options
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\StripeObject
     */
    protected static function _allNestedResources($id, $nestedPath, $params = null, $options = null) {
        $url = static::_nestedResourceUrl($id, $nestedPath);

        return self::_nestedResourceOperation('get', $url, $params, $options);
    }
}
