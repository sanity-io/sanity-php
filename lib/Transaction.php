<?php
namespace Sanity;

use JsonSerializable;
use Sanity\Util\DocumentPropertyAsserter;
use Sanity\Exception\InvalidArgumentException;

class Transaction implements JsonSerializable
{
    use DocumentPropertyAsserter;

    private $client;
    private $operations;

    public function __construct($operations = [], $client = null)
    {
        $this->operations = $operations;
        $this->client = $client;
    }

    /**
     * Create a new document in the configured dataset
     *
     * @param array $document Document to create. Must include a `_type`
     * @return array Returns the transaction
     */
    public function create($document)
    {
        $this->assertProperties($document, ['_type'], 'create');
        $this->operations[] = ['create' => $document];
        return $this;
    }

    /**
     * Create a new document if the document ID does not already exist
     *
     * @param array $document Document to create. Must include `_id`  and `_type`
     * @return array Returns the transaction
     */
    public function createIfNotExists($document)
    {
        $this->assertProperties($document, ['_id', '_type'], 'createIfNotExists');
        $this->operations[] = ['createIfNotExists' => $document];
        return $this;
    }

    /**
     * Create or replace a document with the given ID
     *
     * @param array $document Document to create or replace. Must include `_id`  and `_type`
     * @return array Returns the transaction
     */
    public function createOrReplace($document)
    {
        $this->assertProperties($document, ['_id', '_type'], 'createOrReplace');
        $this->operations[] = ['createOrReplace' => $document];
        return $this;
    }

    /**
     * Deletes document(s) matching the given document ID or query
     *
     * @param string|array|Selection $selection Document ID or a selection
     * @return array Returns the transaction
     */
    public function delete($selection)
    {
        $sel = $selection instanceof Selection ? $selection : new Selection($selection);
        $this->operations[] = ['delete' => $sel->serialize()];
        return $this;
    }

    /**
     * Patch the given document or selection
     *
     * If a patch is passed as the first argument, it will be used directly.
     *
     * @param string|array|Selection|Patch $selection Document ID, selection or a patch to apply
     * @param array $operations Optional array of initial patches
     * @return Sanity\Patch
     */
    public function patch($selection, $operations = null)
    {
        $isPatch = $selection instanceof Patch;
        $patch = $isPatch ? $selection : null;

        if ($isPatch) {
            $this->operations[] = ['patch' => $patch->serialize()];
            return $this;
        }

        if (!is_array($operations)) {
            throw new InvalidArgumentException(
                '`patch` requires either an instantiated patch or an array of patch operations'
            );
        }

        $sel = (new Selection($selection))->serialize();
        $this->operations[] = ['patch' => array_merge($sel, $operations)];
        return $this;
    }

    public function serialize()
    {
        return $this->operations;
    }

    public function jsonSerialize()
    {
        return $this->serialize();
    }

    public function commit($options = null)
    {
        if (!$this->client) {
            throw new Exception\ConfigException(
                'No "client" passed to transaction, either provide one or '
                . 'pass the transaction to a clients `mutate()` method'
            );
        }

        $opts = array_replace(['returnDocuments' => false], $options ?: []);
        return $this->client->mutate($this->serialize(), $opts);
    }

    public function reset()
    {
        $this->operations = [];
        return $this;
    }
}
