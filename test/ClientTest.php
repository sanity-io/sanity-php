<?php
namespace SanityTest;

use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;

use Sanity\Client;
use Sanity\Patch;
use Sanity\Transaction;

class ClientTest extends TestCase
{
    private $client;
    private $history;

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

    public function testCanSetAndGetConfig()
    {
        $this->client = new Client(['projectId' => 'abc', 'dataset' => 'production']);
        $this->assertEquals('production', $this->client->config()['dataset']);
        $this->assertEquals($this->client, $this->client->config(['dataset' => 'staging']));
        $this->assertEquals('staging', $this->client->config()['dataset']);
    }

    public function testCanCreateProjectlessClient()
    {
        $mockBody = ['some' => 'response'];

        $this->history = [];
        $historyMiddleware = Middleware::history($this->history);

        $stack = HandlerStack::create(new MockHandler([$this->mockJsonResponseBody($mockBody)]));
        $stack->push($historyMiddleware);

        $this->client = new Client([
            'useProjectHostname' => false,
            'handler' => $stack,
            'token' => 'mytoken',
        ]);

        $response = $this->client->request(['url' => '/projects']);
        $this->assertEquals($mockBody, $response);
    }

    public function testCanGetDocument()
    {
        $expected = ['_id' => 'someDocId', '_type' => 'bike', 'name' => 'Tandem Extraordinaire'];
        $mockBody = ['documents' => [$expected]];
        $this->mockResponses([$this->mockJsonResponseBody($mockBody)]);

        $this->assertEquals($expected, $this->client->getDocument('someDocId'));
        $this->assertPreviousRequest(['url' => 'https://abc.api.sanity.io/v1/data/doc/production/someDocId']);
        $this->assertPreviousRequest(['headers' => ['Sanity-Token' => 'muchsecure']]);
    }

    public function testCanQueryForDocumentsWithoutParams()
    {
        $query = '*[seats >= 2]';
        $expected = [['_id' => 'someDocId', '_type' => 'bike', 'name' => 'Tandem Extraordinaire', 'seats' => 2]];
        $mockBody = ['result' => $expected];
        $this->mockResponses([$this->mockJsonResponseBody($mockBody)]);

        $this->assertEquals($expected, $this->client->fetch($query));
        $this->assertPreviousRequest([
            'url' => 'https://abc.api.sanity.io/v1/data/query/production?query=%2A%5Bseats%20%3E%3D%202%5D',
            'headers' => ['Sanity-Token' => 'muchsecure'],
        ]);
    }

    public function testCanQueryForDocumentsWithParams()
    {
        $expected = [['_id' => 'someDocId', '_type' => 'bike', 'name' => 'Tandem Extraordinaire', 'seats' => 2]];
        $mockBody = ['result' => $expected];
        $this->mockResponses([$this->mockJsonResponseBody($mockBody)]);

        $query = '*[seats >= $minSeats]';
        $params = ['minSeats' => 2];

        $expectedUrl = 'https://abc.api.sanity.io/v1/data/query/production?';
        $expectedUrl .= 'query=%2A%5Bseats%20%3E%3D%20%24minSeats%5D&%24minSeats=2';

        $this->assertEquals($expected, $this->client->fetch($query, $params));
        $this->assertPreviousRequest([
            'url' => $expectedUrl,
            'headers' => ['Sanity-Token' => 'muchsecure'],
        ]);
    }

    public function testCanQueryForDocumentsWithoutFilteringResponse()
    {
        $query = '*[seats >= 2]';
        $results = [['_id' => 'someDocId', '_type' => 'bike', 'name' => 'Tandem Extraordinaire', 'seats' => 2]];
        $mockBody = ['result' => $results];
        $this->mockResponses([$this->mockJsonResponseBody($mockBody)]);

        $this->assertEquals($mockBody, $this->client->fetch($query, null, ['filterResponse' => false]));
        $this->assertPreviousRequest([
            'url' => 'https://abc.api.sanity.io/v1/data/query/production?query=%2A%5Bseats%20%3E%3D%202%5D',
            'headers' => ['Sanity-Token' => 'muchsecure'],
        ]);
    }

    public function testCanCreateDocument()
    {
        $document = ['_type' => 'bike', 'seats' => 12, 'name' => 'Dusinsykkel'];
        $result = ['_id' => 'someNewDocId'] + $document;
        $mockBody = ['results' => [['id' => 'someNewDocId', 'document' => $result]]];
        $this->mockResponses([$this->mockJsonResponseBody($mockBody)]);

        $this->assertEquals($result, $this->client->create($document));
        $this->assertPreviousRequest([
            'url' => 'https://abc.api.sanity.io/v1/data/mutate/production?returnIds=true&returnDocuments=true',
            'headers' => ['Sanity-Token' => 'muchsecure'],
            'requestBody' => json_encode(['mutations' => [['create' => $document]]])
        ]);
    }

    /**
     * @expectedException Sanity\Exception\InvalidArgumentException
     * @expectedExceptionMessage _type
     */
    public function testThrowsWhenCreatingDocumentWithoutType()
    {
        $this->mockResponses([]);
        $this->client->create(['foo' => 'bar']);
    }

    public function testCanCreateDocumentWithVisibilityOption()
    {
        $document = ['_type' => 'bike', 'seats' => 12, 'name' => 'Dusinsykkel'];
        $result = ['_id' => 'someNewDocId'] + $document;
        $mockBody = ['results' => [['id' => 'someNewDocId', 'document' => $result]]];
        $this->mockResponses([$this->mockJsonResponseBody($mockBody)]);

        $this->assertEquals($result, $this->client->create($document, ['visibility' => 'async']));
        $this->assertPreviousRequest([
            'url' => 'https://abc.api.sanity.io/v1/data/mutate/production?returnIds=true&returnDocuments=true&visibility=async',
            'headers' => ['Sanity-Token' => 'muchsecure'],
            'requestBody' => json_encode(['mutations' => [['create' => $document]]])
        ]);
    }

    public function testCanCreateDocumentIfNotExists()
    {
        $document = ['_id' => 'foobar', '_type' => 'bike', 'seats' => 12, 'name' => 'Dusinsykkel'];
        $mockBody = ['results' => [['id' => 'foobar', 'document' => $document]]];
        $this->mockResponses([$this->mockJsonResponseBody($mockBody)]);

        $this->assertEquals($document, $this->client->createIfNotExists($document));
        $this->assertPreviousRequest([
            'url' => 'https://abc.api.sanity.io/v1/data/mutate/production?returnIds=true&returnDocuments=true',
            'headers' => ['Sanity-Token' => 'muchsecure'],
            'requestBody' => json_encode(['mutations' => [['createIfNotExists' => $document]]])
        ]);
    }

    /**
     * @expectedException Sanity\Exception\InvalidArgumentException
     * @expectedExceptionMessage _id
     */
    public function testThrowsWhenCallingCreateIfNotExistsWithoutId()
    {
        $this->mockResponses([]);
        $this->client->createIfNotExists(['_type' => 'bike']);
    }

    public function testCanCreateOrReplaceDocument()
    {
        $document = ['_id' => 'foobar', '_type' => 'bike', 'seats' => 12, 'name' => 'Dusinsykkel'];
        $mockBody = ['results' => [['id' => 'foobar', 'document' => $document]]];
        $this->mockResponses([$this->mockJsonResponseBody($mockBody)]);

        $this->assertEquals($document, $this->client->createOrReplace($document));
        $this->assertPreviousRequest([
            'url' => 'https://abc.api.sanity.io/v1/data/mutate/production?returnIds=true&returnDocuments=true',
            'headers' => ['Sanity-Token' => 'muchsecure'],
            'requestBody' => json_encode(['mutations' => [['createOrReplace' => $document]]])
        ]);
    }

    /**
     * @expectedException Sanity\Exception\InvalidArgumentException
     * @expectedExceptionMessage _id
     */
    public function testThrowsWhenCallingCreateOrReplaceWithoutId()
    {
        $this->mockResponses([]);
        $this->client->createOrReplace(['_type' => 'bike']);
    }

    public function testCanGeneratePatch()
    {
        $this->client = new Client(['projectId' => 'abc', 'dataset' => 'production']);
        $this->assertInstanceOf(Patch::class, $this->client->patch('someDocId'));
    }

    public function testCanGeneratePatchWithInitialOperations()
    {
        $this->client = new Client(['projectId' => 'abc', 'dataset' => 'production']);
        $serialized = $this->client->patch('someDocId', ['inc' => ['seats' => 1]])->serialize();
        $this->assertEquals(['id' => 'someDocId', 'inc' => ['seats' => 1]], $serialized);
    }

    public function testCanCommitPatch()
    {
        $document = ['_id' => 'someDocId', '_type' => 'bike', 'seats' => 2];
        $mockBody = ['results' => [['id' => 'someDocId', 'document' => $document]]];
        $this->mockResponses([$this->mockJsonResponseBody($mockBody)]);
        $newDoc = $this->client
            ->patch('someDocId', ['inc' => ['seats' => 1]])
            ->setIfMissing(['seats' => 1])
            ->commit();

        $this->assertEquals($document, $newDoc);
        $this->assertPreviousRequest([
            'url' => 'https://abc.api.sanity.io/v1/data/mutate/production?returnIds=true&returnDocuments=true',
            'headers' => ['Sanity-Token' => 'muchsecure'],
            'requestBody' => json_encode(['mutations' => [['patch' => [
                'id' => 'someDocId',
                'inc' => ['seats' => 1],
                'setIfMissing' => ['seats' => 1]
            ]]]])
        ]);
    }

    public function testCanGenerateTransaction()
    {
        $this->client = new Client(['projectId' => 'abc', 'dataset' => 'production']);
        $this->assertInstanceOf(Transaction::class, $this->client->transaction());
    }

    public function testCanGenerateTransactionWithInitialOperations()
    {
        $this->client = new Client(['projectId' => 'abc', 'dataset' => 'production']);
        $serialized = $this->client->transaction([['create' => ['_type' => 'bike']]])->serialize();
        $this->assertEquals([['create' => ['_type' => 'bike']]], $serialized);
    }

    public function testCanCommitTransaction()
    {
        $mockBody = ['transactionId' => 'moo', 'results' => [['id' => 'someNewDocId', 'operation' => 'create']]];
        $this->mockResponses([$this->mockJsonResponseBody($mockBody)]);
        $result = $this->client
            ->transaction([['create' => ['_type' => 'bike']]])
            ->commit();

        $expected = $mockBody + ['documentIds' => ['someNewDocId']];
        $this->assertEquals($expected, $result);
        $this->assertPreviousRequest([
            'url' => 'https://abc.api.sanity.io/v1/data/mutate/production?returnIds=true',
            'headers' => ['Sanity-Token' => 'muchsecure'],
            'requestBody' => json_encode(['mutations' => [['create' => [
                '_type' => 'bike'
            ]]]])
        ]);
    }

    public function testCanDeleteDocument()
    {
        $mockBody = ['transactionId' => 'fnatt', 'results' => [['id' => 'foobar', 'operation' => 'delete']]];
        $this->mockResponses([$this->mockJsonResponseBody($mockBody)]);

        $expected = $mockBody + ['documentIds' => ['foobar']];
        $this->assertEquals($expected, $this->client->delete('foobar'));
        $this->assertPreviousRequest([
            'url' => 'https://abc.api.sanity.io/v1/data/mutate/production?returnIds=true',
            'headers' => ['Sanity-Token' => 'muchsecure'],
            'requestBody' => json_encode(['mutations' => [['delete' => ['id' => 'foobar']]]])
        ]);
    }

    /**
     * Helpers
     */
    private function mockResponses($mocks)
    {
        $this->history = [];
        $historyMiddleware = Middleware::history($this->history);

        $stack = HandlerStack::create(new MockHandler($mocks));
        $stack->push($historyMiddleware);

        $this->client = new Client([
            'projectId' => 'abc',
            'dataset' => 'production',
            'token' => 'muchsecure',
            'handler' => $stack,
        ]);
    }

    private function mockJsonResponseBody($body, $statusCode = 200)
    {
        return new Response($statusCode, ['Content-Type' => 'application/json'], json_encode($body));
    }

    private function assertRequest($expected, $request)
    {
        if (isset($expected['url'])) {
            $this->assertEquals($expected['url'], (string) $request['request']->getUri());
        }

        if (isset($expected['headers'])) {
            foreach ($expected['headers'] as $header => $value) {
                $this->assertEquals($value, $request['request']->getHeaderLine($header));
            }
        }

        if (isset($expected['requestBody'])) {
            $this->assertEquals($expected['requestBody'], (string) $request['request']->getBody());
        }
    }

    private function assertPreviousRequest($expected)
    {
        $this->assertRequest($expected, $this->history[0]);
    }
}
