<?php
namespace Sanity;

use JsonSerializable;
use Sanity\Exception\InvalidArgumentException;

class Selection implements JsonSerializable
{
    private $selection;

    /**
     * Constructs a new selection
     *
     * @param string|array $selection
     */
    public function __construct($selection)
    {
        $this->selection = $this->normalize($selection);
    }

    /**
     * Serializes the selection for use in requests
     *
     * @return array
     */
    public function jsonSerialize()
    {
        return $this->serialize();
    }

    /**
     * Serializes the selection for use in requests
     *
     * @return array
     */
    public function serialize()
    {
        return $this->selection;
    }

    /**
     * Returns whether or not the selection *can* match multiple documents
     *
     * @return bool
     */
    public function matchesMultiple()
    {
        return !isset($this->selection['id']) || is_array($this->selection['id']);
    }

    /**
     * Validates and normalizes a selection
     *
     * @return array
     * @throws InvalidArgumentException
     */
    private function normalize($selection)
    {
        if (isset($selection['query'])) {
            return ['query' => $selection['query']];
        }

        if (is_string($selection) || (is_array($selection) && isset($selection[0]))) {
            return ['id' => $selection];
        }

        $selectionOpts = implode(PHP_EOL, [
            '',
            '* Document ID (<docId>)',
            '* Array of document IDs',
            '* Array containing "query"',
        ]);

        throw new InvalidArgumentException('Invalid selection, must be one of: ' . $selectionOpts);
    }
}
