<?php
namespace Sanity;

class Validators
{
    /**
     * Validates parameters for an insert operation
     *
     * @param string $at Location at which to insert
     * @param string $selector Selector to match at
     * @param array $items Array items to insert
     * @throws InvalidArgumentException
     */
    public static function validateInsert($at, $selector, $items)
    {
        $insertLocations = ['before', 'after', 'replace'];
        $signature = 'insert(at, selector, items)';

        $index = array_search($at, $insertLocations);
        if ($index === false) {
            $valid = implode(', ', array_map(function ($loc) {
                return '"' . $loc . '"';
            }, $insertLocations));
            throw new InvalidArgumentException($signature . ' takes an "at"-argument which is one of: ' . $valid);
        }

        if (!is_string($selector)) {
            throw new InvalidArgumentException($signature . ' takes a "selector"-argument which must be a string');
        }

        if (!is_array($items)) {
            throw new InvalidArgumentException($signature . ' takes an "items"-argument which must be an array');
        }
    }
}
