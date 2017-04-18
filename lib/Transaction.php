<?php
namespace Sanity;

use JsonSerializable;

class Transaction implements JsonSerializable
{
    private $client;
    private $operations;

    public function __construct($operations = [], $client = null)
    {
        $this->operations = $operations;
        $this->client = $client;
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
                . 'pass the transaction to a clients mutate() method'
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
