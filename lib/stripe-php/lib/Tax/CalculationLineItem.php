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

namespace Stripe\Tax;

/**
 * @property string $id Unique identifier for the object.
 * @property string $object String representing the object's type. Objects of the same type share the same value.
 * @property int $amount The line item amount in the <a href="https://stripe.com/docs/currencies#zero-decimal">smallest currency unit</a>. If <code>tax_behavior=inclusive</code>, then this amount includes taxes. Otherwise, taxes were calculated on top of this amount.
 * @property int $amount_tax The amount of tax calculated for this line item, in the <a href="https://stripe.com/docs/currencies#zero-decimal">smallest currency unit</a>.
 * @property bool $livemode Has the value <code>true</code> if the object exists in live mode or the value <code>false</code> if the object exists in test mode.
 * @property null|string $product The ID of an existing <a href="https://stripe.com/docs/api/products/object">Product</a>.
 * @property int $quantity The number of units of the item being purchased. For reversals, this is the quantity reversed.
 * @property null|string $reference A custom identifier for this line item.
 * @property string $tax_behavior Specifies whether the <code>amount</code> includes taxes. If <code>tax_behavior=inclusive</code>, then the amount includes taxes.
 * @property null|\Stripe\StripeObject[] $tax_breakdown Detailed account of taxes relevant to this line item.
 * @property string $tax_code The <a href="https://stripe.com/docs/tax/tax-categories">tax code</a> ID used for this resource.
 * @package report_adeptus_insights
 */
class CalculationLineItem extends \Stripe\ApiResource
{
    const OBJECT_NAME = 'tax.calculation_line_item';

    const TAX_BEHAVIOR_EXCLUSIVE = 'exclusive';
    const TAX_BEHAVIOR_INCLUSIVE = 'inclusive';
}
