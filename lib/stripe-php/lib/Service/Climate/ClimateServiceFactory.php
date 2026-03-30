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

namespace Stripe\Service\Climate;

/**
 * Service factory class for API resources in the Climate namespace.
 *
 * @property OrderService $orders
 * @property ProductService $products
 * @property SupplierService $suppliers
 * @package report_adeptus_insights
 */
class ClimateServiceFactory extends \Stripe\Service\AbstractServiceFactory
{
    /**
     * @var array<string, string>
     */
    private static $classMap = [
        'orders' => OrderService::class,
        'products' => ProductService::class,
        'suppliers' => SupplierService::class,
    ];

    protected function getServiceClass($name) {
        return \array_key_exists($name, self::$classMap) ? self::$classMap[$name] : null;
    }
}
