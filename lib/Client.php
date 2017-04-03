<?php
namespace Sanity;

use Sanity\Exception\ConfigException;
use Sanity\Exception\ClientException;
use Sanity\Exception\ServerException;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Psr7\Request;

class Client
{
    private $defaultConfig = [
        'apiHost' => 'https://api.sanity.io',
        'apiVersion' => 'v1',
        'useProjectHostname' => true,
        'timeout' => 30,
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
        $mapResponse = !isset($options['filterResponse']) || $options['filterResponse'] !== false;

        $serializedParams = $params ? ParameterSerializer::serialize($params) : [];
        $queryParams = array_merge(['query' => $query], $serializedParams, $options);

        $body = $this->request([
            'url' => '/data/query/' . $this->clientConfig['dataset'],
            'query' => $queryParams,
        ]);

        return $mapResponse ? $body['result'] : $body;
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
            'url' => '/data/doc/' . $this->clientConfig['dataset'] . '/' . $id
        ]);
        return $body['documents'][0];
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
        ]);

        return $this;
    }

    /**
     * Performs a request against the Sanity API based on the passed options.
     *
     * @param array Array of options for this request.
     * @return mixed Returns a decoded response, type varies with endpoint.
     */
    public function request($options)
    {
        $request = $this->getRequest($options);
        $requestOptions = isset($options['query']) ? ['query' => $options['query']] : [];
        $response = $this->httpClient->send($request, $requestOptions);
        $code = $response->getStatusCode();

        if ($code >= 500) {
            throw new ServerException($response);
        } elseif ($code >= 400) {
            throw new ClientException($response);
        }

        $contentType = $response->getHeader('Content-Type')[0];
        $isJson = stripos($contentType, 'application/json') !== false;
        $rawBody = (string) $response->getBody();
        $body = $isJson ? json_decode($rawBody, true) : $rawBody;

        return $body;
    }

    /**
     * Returns an instance of Request based on the given options and client configuration.
     *
     * @param array Array of options for this request.
     * @return GuzzleHttp\Psr7\Request Returns an initialized request.
     */
    private function getRequest($options)
    {
        $headers = isset($options['headers']) ? $options['headers'] : [];
        if (isset($this->clientConfig['token'])) {
            $headers['Sanity-Token'] = $this->clientConfig['token'];
        }

        $method = isset($options['method']) ? $options['method'] : 'GET';
        $body = isset($options['body']) ? $options['body'] : null;
        $url = $this->clientConfig['url'] . $options['url'];

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
        $newConfig = array_replace_recursive($this->defaultConfig, $this->clientConfig, $config);
        $apiVersion = $newConfig['apiVersion'];
        $projectBased = $newConfig['useProjectHostname'];
        $projectId = isset($newConfig['projectId']) ? $newConfig['projectId'] : null;

        if ($projectBased && !$projectId) {
            throw new ConfigException('Configuration must contain `projectId`');
        }

        $hostParts = explode('://', $newConfig['apiHost']);
        $protocol = $hostParts[0];
        $host = $hostParts[1];

        if ($projectBased) {
            $newConfig['url'] = $protocol . '://' . $projectId . '.' . $host . '/' . $apiVersion;
        } else {
            $newConfig['url'] = $newConfig['apiHost'] . $apiVersion;
        }

        return $newConfig;
    }
}
