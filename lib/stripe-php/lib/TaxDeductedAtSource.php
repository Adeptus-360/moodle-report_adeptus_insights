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

namespace Stripe;

/**
 * @property string $id Unique identifier for the object.
 * @property string $object String representing the object's type. Objects of the same type share the same value.
 * @property int $period_end The end of the invoicing period. This TDS applies to Stripe fees collected during this invoicing period.
 * @property int $period_start The start of the invoicing period. This TDS applies to Stripe fees collected during this invoicing period.
 * @property string $tax_deduction_account_number The TAN that was supplied to Stripe when TDS was assessed
 * @package report_adeptus_insights
 */
class TaxDeductedAtSource extends ApiResource
{
    const OBJECT_NAME = 'tax_deducted_at_source';
}
