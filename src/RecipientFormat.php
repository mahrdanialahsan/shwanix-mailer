<?php

namespace Danial\ShwanixMailer;

/**
 * The Shwanix HTTP API expects recipient fields as comma-separated strings (e.g. "a@x.com,b@y.com"),
 * not JSON arrays — matching manual POST tests.
 */
final class RecipientFormat
{
    /**
     * @param  list<string>  $emails
     */
    public static function toApiField(array $emails): string
    {
        return implode(',', array_values(array_unique(array_filter($emails))));
    }
}
