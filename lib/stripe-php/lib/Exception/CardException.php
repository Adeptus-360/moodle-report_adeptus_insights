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

namespace Stripe\Exception;

/**
 * CardException is thrown when a user enters a card that can't be charged for
 * some reason.
 * @package report_adeptus_insights
 */
class CardException extends ApiErrorException
{
    protected $declineCode;
    protected $stripeParam;

    /**
     * Creates a new CardException exception.
     *
     * @param string $message the exception message
     * @param null|int $httpStatus the HTTP status code
     * @param null|string $httpBody the HTTP body as a string
     * @param null|array $jsonBody the JSON deserialized body
     * @param null|array|\Stripe\Util\CaseInsensitiveArray $httpHeaders the HTTP headers array
     * @param null|string $stripeCode the Stripe error code
     * @param null|string $declineCode the decline code
     * @param null|string $stripeParam the parameter related to the error
     *
     * @return CardException
     */
    public static function factory(
        $message,
        $httpStatus = null,
        $httpBody = null,
        $jsonBody = null,
        $httpHeaders = null,
        $stripeCode = null,
        $declineCode = null,
        $stripeParam = null
    ) {
        $instance = parent::factory($message, $httpStatus, $httpBody, $jsonBody, $httpHeaders, $stripeCode);
        $instance->setDeclineCode($declineCode);
        $instance->setStripeParam($stripeParam);

        return $instance;
    }

    /**
     * Gets the decline code.
     *
     * @return null|string
     */
    public function getDeclineCode() {
        return $this->declineCode;
    }

    /**
     * Sets the decline code.
     *
     * @param null|string $declineCode
     */
    public function setDeclineCode($declineCode) {
        $this->declineCode = $declineCode;
    }

    /**
     * Gets the parameter related to the error.
     *
     * @return null|string
     */
    public function getStripeParam() {
        return $this->stripeParam;
    }

    /**
     * Sets the parameter related to the error.
     *
     * @param null|string $stripeParam
     */
    public function setStripeParam($stripeParam) {
        $this->stripeParam = $stripeParam;
    }
}
