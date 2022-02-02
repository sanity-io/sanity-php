<?php
namespace Sanity;

use JsonSerializable;
use Sanity\Util\DocumentPropertyAsserter;
use Sanity\Exception\InvalidArgumentException;

class Patch implements JsonSerializable
{
    use DocumentPropertyAsserter;

    private $client;
    private $selection;
    private $operations;

    public function __construct($selection, $operations = [], $client = null)
    {
        $this->client = $client;
        $this->operations = $operations;

        $this->selection = $selection instanceof Selection
            ? $selection
            : new Selection($selection);
    }

    public function merge($props)
    {
        $previous = isset($this->operations['merge']) ? $this->operations['merge'] : [];
        $this->operations['merge'] = array_replace_recursive($previous, $props);
        return $this;
    }

    public function set($props)
    {
        return $this->assign('set', $props);
    }

    public function setIfMissing($props)
    {
        return $this->assign('setIfMissing', $props);
    }

    public function diffMatchPatch($props)
    {
        return $this->assign('diffMatchPatch', $props);
    }

    public function remove($attrs)
    {
        if (!is_array($attrs)) {
            throw new InvalidArgumentException(
                'remove(attrs) takes an array of attributes to unset, non-array given'
            );
        }

        $previous = isset($this->operations['unset']) ? $this->operations['unset'] : [];
        $merged = array_unique(array_merge($previous, $attrs));
        $this->operations['unset'] = $merged;
        return $this;
    }

    public function replace($props)
    {
        $this->operations['set'] = ['$' => $props];
        return $this;
    }

    private function assign($operation, $props, $merge = true)
    {
        $previous = isset($this->operations[$operation]) ? $this->operations[$operation] : [];
        $this->operations[$operation] = $merge ? array_replace($previous, $props) : $props;
        return $this;
    }

    public function inc($props)
    {
        return $this->assign('inc', $props);
    }

    public function dec($props)
    {
        return $this->assign('dec', $props);
    }

    public function insert($at, $selector, $items)
    {
        $this->validateInsert($at, $selector, $items);
        return $this->assign('insert', [$at => $selector, 'items' => $items]);
    }

    public function append($selector, $items)
    {
        return $this->insert('after', $selector . '[-1]', $items);
    }

    public function prepend($selector, $items)
    {
        return $this->insert('before', $selector . '[0]', $items);
    }

    public function splice($selector, $start, $deleteCount = null, $items = null)
    {
        // Negative indexes doesn't mean the same in Sanity as they do in PHP;
        // -1 means "actually at the end of the array", which allows inserting
        // at the end of the array without knowing its length. We therefore have
        // to substract negative indexes by one to match PHP. If you want Sanity-
        // behaviour, just use `insert('replace', selector, items)` directly
        $delAll = $deleteCount === null || $deleteCount === -1;
        $startIndex = $start < 0 ? $start - 1 : $start;
        $delCount = $delAll ? -1 : max(0, $start + $deleteCount);
        $delRange = $startIndex < 0 && $delCount >= 0 ? '' : $delCount;
        $rangeSelector = $selector . '[' . $startIndex . ':' . $delRange . ']';
        return $this->insert('replace', $rangeSelector, $items ?: []);
    }

    public function serialize()
    {
        return array_replace(
            $this->selection->serialize(),
            $this->operations
        );
    }

    public function jsonSerialize()
    {
        return $this->serialize();
    }

    public function commit($options = null)
    {
        if (!$this->client) {
            throw new Exception\ConfigException(
                'No "client" passed to patch, either provide one or pass the patch to a clients mutate() method'
            );
        }

        $returnFirst = $this->selection->matchesMultiple() === false;
        $opts = array_replace(['returnFirst' => $returnFirst, 'returnDocuments' => true], $options ?: []);
        return $this->client->mutate(['patch' => $this->serialize()], $opts);
    }

    public function reset()
    {
        $this->operations = [];
        return $this;
    }

    /**
     * Validates parameters for an insert operation
     *
     * @param string $at Location at which to insert
     * @param string $selector Selector to match at
     * @param array $items Array items to insert
     * @throws InvalidArgumentException
     */
    private function validateInsert($at, $selector, $items)
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
