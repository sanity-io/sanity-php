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

    public function __construct($config = [])
    {
        $this->config($config);
    }

    /**
     * Data methods
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
     * Client configuration and helper methods
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
