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

namespace Stripe;

abstract class Webhook
{
    const DEFAULT_TOLERANCE = 300;

    /**
     * Returns an Events instance using the provided JSON payload. Throws an
     * Exception\UnexpectedValueException if the payload is not valid JSON, and
     * an Exception\SignatureVerificationException if the signature
     * verification fails for any reason.
     *
     * @param string $payload the payload sent by Stripe
     * @param string $sigHeader the contents of the signature header sent by
     *  Stripe
     * @param string $secret secret used to generate the signature
     * @param int $tolerance maximum difference allowed between the header's
     *  timestamp and the current time
     *
     * @throws Exception\UnexpectedValueException if the payload is not valid JSON,
     * @throws Exception\SignatureVerificationException if the verification fails
     *
     * @return Event the Events instance
     */
    public static function constructEvent($payload, $sigHeader, $secret, $tolerance = self::DEFAULT_TOLERANCE) {
        WebhookSignature::verifyHeader($payload, $sigHeader, $secret, $tolerance);

        $data = \json_decode($payload, true);
        $jsonError = \json_last_error();
        if (null === $data && \JSON_ERROR_NONE !== $jsonError) {
            $msg = "Invalid payload: {$payload} "
              . "(json_last_error() was {$jsonError})";

            throw new Exception\UnexpectedValueException($msg);
        }

        return Event::constructFrom($data);
    }
}
