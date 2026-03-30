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

namespace Stripe\Service\TestHelpers;

/**
 * Service factory class for API resources in the TestHelpers namespace.
 *
 * @property ConfirmationTokenService $confirmationTokens
 * @property CustomerService $customers
 * @property Issuing\IssuingServiceFactory $issuing
 * @property RefundService $refunds
 * @property Terminal\TerminalServiceFactory $terminal
 * @property TestClockService $testClocks
 * @property Treasury\TreasuryServiceFactory $treasury
 * @package report_adeptus_insights
 */
class TestHelpersServiceFactory extends \Stripe\Service\AbstractServiceFactory
{
    /**
     * @var array<string, string>
     */
    private static $classMap = [
        'confirmationTokens' => ConfirmationTokenService::class,
        'customers' => CustomerService::class,
        'issuing' => Issuing\IssuingServiceFactory::class,
        'refunds' => RefundService::class,
        'terminal' => Terminal\TerminalServiceFactory::class,
        'testClocks' => TestClockService::class,
        'treasury' => Treasury\TreasuryServiceFactory::class,
    ];

    protected function getServiceClass($name) {
        return \array_key_exists($name, self::$classMap) ? self::$classMap[$name] : null;
    }
}
