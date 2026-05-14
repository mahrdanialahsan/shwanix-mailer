<?php

namespace Danial\ShwanixMailer;

/**
 * Shwanix Mail API recipient rules (see Postman collection):
 * - `to`: string (comma-separated if multiple).
 * - `cc` / `bcc`: JSON array of addresses, or a single string; omit when empty.
 */
final class RecipientFormat
{
    /**
     * @param  list<string>  $emails
     * @return list<string>
     */
    public static function normalize(array $emails): array
    {
        return array_values(array_unique(array_filter($emails)));
    }

    /**
     * Primary recipients as one string (comma-separated when multiple).
     *
     * @param  list<string>  $emails
     */
    public static function toApiTo(array $emails): string
    {
        return implode(',', self::normalize($emails));
    }

    /**
     * @param  list<string>  $emails
     * @return array<int, string>|string|null null = omit field
     */
    public static function toApiCcBcc(array $emails): array|string|null
    {
        $e = self::normalize($emails);
        if ($e === []) {
            return null;
        }
        if (count($e) === 1) {
            return $e[0];
        }

        return $e;
    }
}
