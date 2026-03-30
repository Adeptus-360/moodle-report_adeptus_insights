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
class FileService extends \Stripe\Service\AbstractService
{
    /**
     * Returns a list of the files that your account has access to. Stripe sorts and
     * returns the files by their creation dates, placing the most recently created
     * files at the top.
     *
     * @param null|array $params
     * @param null|RequestOptionsArray|\Stripe\Util\RequestOptions $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\Collection<\Stripe\File>
     */
    public function all($params = null, $opts = null) {
        return $this->requestCollection('get', '/v1/files', $params, $opts);
    }

    /**
     * Retrieves the details of an existing file object. After you supply a unique file
     * ID, Stripe returns the corresponding file object. Learn how to <a
     * href="/docs/file-upload#download-file-contents">access file contents</a>.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\Stripe\Util\RequestOptions $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\File
     */
    public function retrieve($id, $params = null, $opts = null) {
        return $this->request('get', $this->buildPath('/v1/files/%s', $id), $params, $opts);
    }

    /**
     * Create a file.
     *
     * @param null|array $params
     * @param null|array|\Stripe\Util\RequestOptions $opts
     *
     * @return \Stripe\File
     */
    public function create($params = null, $opts = null) {
        $opts = \Stripe\Util\RequestOptions::parse($opts);
        if (!isset($opts->apiBase)) {
            $opts->apiBase = $this->getClient()->getFilesBase();
        }

        // Manually flatten params, otherwise curl's multipart encoder will
        // choke on nested null|arrays.
        $flatParams = \array_column(\Stripe\Util\Util::flattenParams($params), 1, 0);

        return $this->request('post', '/v1/files', $flatParams, $opts);
    }
}
