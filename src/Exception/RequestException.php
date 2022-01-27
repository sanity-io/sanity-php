<?php
namespace Sanity\Exception;

class RequestException extends BaseException
{
    protected $response;
    protected $statusCode;
    protected $responseBody;

    public function __construct($response)
    {
        $code = $response->getStatusCode();
        $reason = $response->getReasonPhrase();
        $contentType = $response->getHeader('Content-Type')[0];
        $isJson = stripos($contentType, 'application/json') !== false;
        $rawBody = (string) $response->getBody();
        $body = $isJson ? json_decode($rawBody, true) : $rawBody;

        $this->response = $response;
        $this->statusCode = $code;
        $this->responseBody = $rawBody;

        if (isset($body['error']) && isset($body['message'])) {
            // API/Boom style errors ({statusCode, error, message})
            $this->message = $body['error'] . ' - ' . $body['message'];
        } elseif (isset($body['error']) && isset($body['error']['description'])) {
            // Query/database errors ({error: {description, other, arb, props}})
            $this->message = $body['error']['description'];
            $this->details = $body['error'];
        } else {
            // Other, more arbitrary errors
            $this->message = $this->resolveErrorMessage($body);
        }

        parent::__construct($this->message, $code);
    }

    public function getResponse()
    {
        return $this->response;
    }

    public function getResponseBody()
    {
        return $this->responseBody;
    }

    public function getStatusCode()
    {
        return $this->statusCode;
    }

    private function resolveErrorMessage($body)
    {
        if (isset($body['error'])) {
            return $body['error'];
        }

        if (isset($body['message'])) {
            return $body['message'];
        }

        return 'Unknown error; body: ' . json_encode($body);
    }
}
