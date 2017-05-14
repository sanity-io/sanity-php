<?php
namespace Sanity\Util;

use Sanity\Exception\InvalidArgumentException;

trait DocumentPropertyAsserter
{
    /**
     * Assert that the given document contains the required properties
     *
     * @param array $doc Document to validate
     * @param array $required Array of properties to assert existence of
     * @param string $method Method which require the properties
     * @throws InvalidArgumentException
     */
    private function assertProperties($doc, $required, $method)
    {
        $keys = array_keys($doc);
        $diff = array_diff($required, $keys);

        if (empty($diff)) {
            return;
        }

        $err = $method . ': the following required properties are missing: ';
        $err .= implode(', ', $diff);
        throw new InvalidArgumentException($err);
    }
}
