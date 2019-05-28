<?php
namespace Sanity;

use Exception;
use DateInterval;
use DateTimeImmutable;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Exception\RequestException as GuzzleRequestException;
use Sanity\BlockContent;
use Sanity\Exception\ClientException;
use Sanity\Exception\ConfigException;
use Sanity\Exception\ServerException;
use Sanity\Exception\InvalidArgumentException;
use Sanity\Util\DocumentPropertyAsserter;

class Client
{
    use DocumentPropertyAsserter;

    const NO_API_VERSION_WARNING =
        'Using the Sanity client without specifying an API version is deprecated.' .
        'See https://github.com/sanity-io/sanity-php#specifying-api-version';

    private $defaultConfig = [
        'apiHost' => 'https://api.sanity.io',
        'apiVersion' => '1',
        'useProjectHostname' => true,
        'timeout' => 30,
        'handler' => null,
    ];

    private $clientConfig = [];
    private $httpClient;

    /**
     * Creates a new instance of the Sanity client
     *
     * @param array $config Array of configuration options
     * @return Client
     */
    public function __construct($config = [])
    {
        $this->config($config);
    }

    /**
     * Query for documents
     *
     * Given a GROQ-query and an optional set of parameters, run a query against the API
     * and return the decoded result.
     *
     * @param string $query GROQ-query to send to the API
     * @param array $params Associative array of parameters to use for the query
     * @param array $options Optional array of options for the query operation
     * @return mixed Returns the result - data type depends on the query
     */
    public function fetch($query, $params = null, $options = [])
    {
        $unfilteredResponse = isset($options['filterResponse']) && $options['filterResponse'] === false;

        $serializedParams = $params ? ParameterSerializer::serialize($params) : [];
        $queryParams = array_merge(['query' => $query], $serializedParams);

        $body = $this->request([
            'url' => '/data/query/' . $this->clientConfig['dataset'],
            'query' => $queryParams,
            'cdnAllowed' => true
        ]);

        return $unfilteredResponse ? $body :  $body['result'];
    }

    /**
     * Fetch a single document by ID
     *
     * @param string $id ID of the document to retrieve
     * @return array Returns an associative array
     */
    public function getDocument($id)
    {
        $body = $this->request([
            'url' => '/data/doc/' . $this->clientConfig['dataset'] . '/' . $id,
            'cdnAllowed' => true
        ]);
        return $body['documents'][0];
    }

    /**
     * Create a new document in the configured dataset
     *
     * @param array $document Document to create. Must include a `_type`
     * @param array $options Optional request options
     * @return array Returns the created document
     */
    public function create($document, $options = null)
    {
        $this->assertProperties($document, ['_type'], 'create');
        return $this->createDocument($document, 'create', $options);
    }

    /**
     * Create a new document if the document ID does not already exist
     *
     * @param array $document Document to create. Must include `_id`  and `_type`
     * @param array $options Optional request options
     * @return array Returns the created document
     */
    public function createIfNotExists($document, $options = null)
    {
        $this->assertProperties($document, ['_id', '_type'], 'createIfNotExists');
        return $this->createDocument($document, 'createIfNotExists', $options);
    }

    /**
     * Create or replace a document with the given ID
     *
     * @param array $document Document to create. Must include `_id`  and `_type`
     * @param array $options Optional request options
     * @return array Returns the created document
     */
    public function createOrReplace($document, $options = null)
    {
        $this->assertProperties($document, ['_id', '_type'], 'createOrReplace');
        return $this->createDocument($document, 'createOrReplace', $options);
    }

    /**
     * Return an instance of Sanity\Patch for the given document ID or query
     *
     * @param string|array|Selection $selection Document ID or a selection
     * @param array $operations Optional array of initial patches
     * @return Sanity\Patch
     */
    public function patch($selection, $operations = null)
    {
        return new Patch($selection, $operations, $this);
    }

    /**
     * Return an instance of Sanity\Transaction bound to the current client
     *
     * @param array $operations Optional array of initial mutations
     * @return Sanity\Transaction
     */
    public function transaction($operations = null)
    {
        return new Transaction($operations, $this);
    }

    /**
     * Deletes document(s) matching the given document ID or query
     *
     * @param string|array|Selection $selection Document ID or a selection
     * @param array $options Optional array of options for the request
     * @return array Returns the mutation result
     */
    public function delete($selection, $options = null)
    {
        $sel = $selection instanceof Selection ? $selection : new Selection($selection);
        $opts = array_replace(['returnDocuments' =>  false], $options ?: []);
        return $this->mutate(['delete' => $sel->serialize()], $opts);
    }

    /**
     * Send a set of mutations to the API for processing
     *
     * @param array|Patch|Transaction $mutations Either an array of mutations, a patch or a transaction
     * @param array $options Optional array of options for the request
     * @return array Mutation result
     */
    public function mutate($mutations, $options = null)
    {
        $mut = $mutations;
        if ($mut instanceof Patch) {
            $mut = ['patch' => $mut->serialize()];
        } elseif ($mut instanceof Transaction) {
            $mut = $mut->serialize();
        }

        $body = ['mutations' => !isset($mut[0]) ? [$mut] : $mut];
        $queryParams = $this->getMutationQueryParams($options);
        $requestOptions = [
            'method' => 'POST',
            'query' => $queryParams,
            'headers' => ['Content-Type: application/json'],
            'body' => json_encode($body),
            'url' => '/data/mutate/' . $this->clientConfig['dataset'],
        ];

        // Try to perform request
        $body = $this->request($requestOptions);

        // Should we return the documents?
        $returnDocuments = isset($options['returnDocuments']) && $options['returnDocuments'];
        $returnFirst = isset($options['returnFirst']) && $options['returnFirst'];
        $results = isset($body['results']) ? $body['results'] : [];

        if ($returnDocuments && $returnFirst) {
            return isset($results[0]) ? $results[0]['document'] : null;
        } elseif ($returnDocuments) {
            return array_column($results, 'document', 'id');
        }

        // Return a reduced subset
        if ($returnFirst) {
            $ids = isset($results[0]) ? $results[0]['id'] : null;
        } else {
            $ids = array_column($results, 'id');
        }

        $key = $returnFirst ? 'documentId' : 'documentIds';
        return [
          'transactionId' => $body['transactionId'],
          'results' => $results,
          $key => $ids,
        ];
    }

    /**
     * Sets or gets the client configuration.
     *
     * If a new configuration is passed as the first argument, it will be merged
     * with the existing configuration. If no new configuration is given, the old
     * configuration is returned.
     *
     * @param array|null $newConfig New configuration to use.
     * @return array|Client Returns the client instance if a new configuration is passed
     */
    public function config($newConfig = null)
    {
        if ($newConfig === null) {
            return $this->clientConfig;
        }

        $this->clientConfig = $this->initConfig($newConfig);

        $this->httpClient = new HttpClient([
            'base_uri' => $this->clientConfig['url'],
            'timeout' => $this->clientConfig['timeout'],
            'handler' => $this->clientConfig['handler'],
        ]);

        return $this;
    }

    /**
     * Performs a request against the Sanity API based on the passed options.
     *
     * @param array $options Array of options for this request.
     * @return mixed Returns a decoded response, type varies with endpoint.
     */
    public function request($options)
    {
        $request = $this->getRequest($options);
        $requestOptions = isset($options['query']) ? ['query' => $options['query']] : [];

        // Try to perform request
        try {
            $response = $this->httpClient->send($request, $requestOptions);
        } catch (GuzzleRequestException $err) {
            $hasResponse = $err->hasResponse();
            if (!$hasResponse) {
                // @todo how do we handle, wrap guzzle err
                throw $err;
            }

            $response = $err->getResponse();
            $code = $response->getStatusCode();

            if ($code >= 500) {
                throw new ServerException($response);
            } elseif ($code >= 400) {
                throw new ClientException($response);
            }
        }

        $warnings = $response->getHeader('X-Sanity-Warning');
        foreach ($warnings as $warning) {
            trigger_error($warning, E_USER_WARNING);
        }

        $contentType = $response->getHeader('Content-Type')[0];
        $isJson = stripos($contentType, 'application/json') !== false;
        $rawBody = (string) $response->getBody();
        $body = $isJson ? json_decode($rawBody, true) : $rawBody;

        return $body;
    }

    /**
     * Creates a document using the given operation type
     *
     * @param array $document Document to create
     * @param string $operation Operation to use (create/createIfNotExists/createOrReplace)
     * @param array $options
     * @return array Returns the created document, or the mutation result if returnDocuments is false
     */
    private function createDocument($document, $operation, $options = [])
    {
        $mutation = [$operation => $document];
        $opts = array_replace(['returnFirst' => true, 'returnDocuments' => true], $options ?: []);
        return $this->mutate([$mutation], $opts);
    }

    /**
     * Returns an instance of Request based on the given options and client configuration.
     *
     * @param array $options Array of options for this request.
     * @return GuzzleHttp\Psr7\Request Returns an initialized request.
     */
    private function getRequest($options)
    {
        $headers = isset($options['headers']) ? $options['headers'] : [];
        $headers['User-Agent'] = 'sanity-php ' . Version::VERSION;

        if (!empty($this->clientConfig['token'])) {
            $headers['Authorization'] = 'Bearer ' . $this->clientConfig['token'];
        }

        $method = isset($options['method']) ? $options['method'] : 'GET';
        $body = isset($options['body']) ? $options['body'] : null;
        $cdnAllowed = (
            isset($options['cdnAllowed']) &&
            $options['cdnAllowed'] &&
            $this->clientConfig['useCdn']
        );

        $baseUrl = $cdnAllowed
            ? $this->clientConfig['cdnUrl']
            : $this->clientConfig['url'];

        $url = $baseUrl . $options['url'];

        return new Request($method, $url, $headers, $body);
    }

    /**
     * Initialize a new client configuration
     *
     * Validate a configuration, merging default values and assigning the
     * correct hostname based on project ID.
     *
     * @param array $config New configuration parameters to use.
     * @return array Returns the new configuration.
     */
    private function initConfig($config)
    {
        $specifiedConfig = array_replace_recursive($this->clientConfig, $config);
        if (!isset($specifiedConfig['apiVersion'])) {
            trigger_error(Client::NO_API_VERSION_WARNING, E_USER_DEPRECATED);
        }

        $newConfig = array_replace_recursive($this->defaultConfig, $specifiedConfig);
        $apiVersion = str_replace('#^v#', '', $newConfig['apiVersion']);
        $projectBased = $newConfig['useProjectHostname'];
        $useCdn = isset($newConfig['useCdn']) ? $newConfig['useCdn'] : false;
        $projectId = isset($newConfig['projectId']) ? $newConfig['projectId'] : null;
        $dataset = isset($newConfig['dataset']) ? $newConfig['dataset'] : null;

        $apiIsDate = preg_match('#^\d{4}-\d{2}-\d{2}$#', $apiVersion);
        if ($apiIsDate) {
            try {
                new DateTimeImmutable($apiVersion);
            } catch (Exception $err) {
                throw new ConfigException('Invalid ISO-date "' . $apiVersion . '"');
            }
        } elseif ($apiVersion !== 'X' && $apiVersion !== '1') {
            throw new ConfigException('Invalid API version, must be either a date in YYYY-MM-DD format, `1` or `X`');
        }

        if ($projectBased && !$projectId) {
            throw new ConfigException('Configuration must contain `projectId`');
        }

        if ($projectBased && !$dataset) {
            throw new ConfigException('Configuration must contain `dataset`');
        }

        if ($useCdn && !empty($newConfig['token'])) {
            throw new ConfigException(
                'Cannot combine `useCdn` option with `token` as authenticated requests cannot be cached'
            );
        }

        $hostParts = explode('://', $newConfig['apiHost']);
        $protocol = $hostParts[0];
        $host = $hostParts[1];

        if ($projectBased) {
            $newConfig['url'] = $protocol . '://' . $projectId . '.' . $host . '/v' . $apiVersion;
        } else {
            $newConfig['url'] = $newConfig['apiHost'] . '/v' . $apiVersion;
        }

        $newConfig['useCdn'] = $useCdn;
        $newConfig['cdnUrl'] = preg_replace('#(/|\.)api.sanity.io/#', '$1apicdn.sanity.io/', $newConfig['url']);
        return $newConfig;
    }

    /**
     * Get a reduced and normalized set of query params for a mutation based on the given options
     *
     * @param array $options Array of request options
     * @return array Array of normalized query params
     */
    private function getMutationQueryParams($options = [])
    {
        $query = ['returnIds' => 'true'];

        if (!isset($options['returnDocuments']) || $options['returnDocuments']) {
            $query['returnDocuments'] = 'true';
        }

        if (isset($options['visibility']) && $options['visibility'] !== 'sync') {
            $query['visibility'] = $options['visibility'];
        }

        return $query;
    }
}
