<?php
namespace SanityTest;

use Sanity\Client;

class ClientTest extends TestCase
{
    private $client;

    public function __construct()
    {
        $this->client = new Client([
            'projectId' => 'abc',
            'dataset' => 'production'
        ]);
    }

    public function testCanConstructNewClient()
    {
        $this->client = new Client(['projectId' => 'abc', 'dataset' => 'production']);
        $this->assertInstanceOf(Client::class, $this->client);
    }

    /**
     * @expectedException Sanity\Exception\ConfigException
     * @expectedExceptionMessage Configuration must contain `projectId`
     */
    public function testThrowsWhenConstructingClientWithoutProjectId()
    {
        $this->client = new Client(['dataset' => 'production']);
    }

    /**
     * @expectedException Sanity\Exception\ConfigException
     * @expectedExceptionMessage Configuration must contain `dataset`
     */
    public function testThrowsWhenConstructingClientWithoutDataset()
    {
        $this->client = new Client(['projectId' => 'abc']);
    }
}
