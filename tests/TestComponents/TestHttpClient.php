<?php

namespace Patagona\Pricemonitor\Core\Tests\TestComponents;

use Patagona\Pricemonitor\Core\Interfaces\HttpClient;
use Patagona\Pricemonitor\Core\Interfaces\HttpResponse;

class TestHttpClient implements HttpClient
{
    const REQUEST_TYPE_SYNC = 'sync';
    const REQUEST_TYPE_ASYNC = 'async';

    private $history = [];
    private $responses = [];

    /**
     * Sends HTTP request to specified URL with using given request method, headers and body
     *
     * @param string $method HTTP request method ("GET", "POST", "PUT",...)
     * @param string $url Request URL. Full URL where request should be sent
     * @param array $headers Request headers to send. Use key as header name and value as header content. Example:
     *                  request("GET", "https://app.patagona.de/api/account", [ "Authorization" "Basic XXXXXX==" ])
     * @param string $body Request payload. String data to send as HTTP request payload
     *
     * @return HttpResponse Response from the server
     * @throws \Exception Throws exception if communication is impossible due to no internet connection for example.
     * Do not throw exception when server respond with error status code (500 internal server error for example), in
     * this cases return response regularly via HttpResponse with 500 status code.
     */
    public function request($method, $url, array $headers = [], $body = '')
    {
        $this->history[] = [
            'type' => self::REQUEST_TYPE_SYNC,
            'method' => $method,
            'url' => $url,
            'headers' => $headers,
            'body' => $body,
        ];

        if (empty($this->responses)) {
            throw new \Exception('Internet connection problem test message');
        }

        $response = array_shift($this->responses);
        if ($response instanceof \Exception) {
            throw $response;
        }

        return $response;
    }

    /**
     * Sends asynchronous HTTP request to specified URL with using given request method, headers and body. Method should
     * not block process and wait for response, it must send request without waiting for result (fire and forget).
     *
     * @param string $method HTTP request method ("GET", "POST", "PUT",...)
     * @param string $url Request URL. Full URL where request should be sent
     * @param array $headers Request headers to send. Use key as header name and value as header content. Example:
     *                  request("GET", "https://app.patagona.de/api/account", [ "Authorization" "Basic XXXXXX==" ])
     * @param string $body Request payload. String data to send as HTTP request payload
     *
     * @throws \Exception Throws exception if communication is impossible due to no internet connection for example.
     */
    public function requestAsync($method, $url, array $headers = [], $body = '')
    {
        $this->history[] = [
            'type' => self::REQUEST_TYPE_ASYNC,
            'method' => $method,
            'url' => $url,
            'headers' => $headers,
            'body' => $body,
        ];
    }

    /**
     * @return array History of all requests made with TestHttpClient
     */
    public function getHistory()
    {
        return $this->history;
    }

    /**
     * Get the last received request.
     *
     * @return array
     */
    public function getLastRequest()
    {
        $lastRequest = end($this->history);
        reset($this->history);

        return $lastRequest;
    }

    /**
     * Appends mock responses to be used as a response for called requests
     *
     * @param array $responses List of response instances
     */
    public function appendMockResponses(array $responses)
    {
        $this->responses = array_merge($this->responses, $responses);
    }

    /**
     * Sets mock responses to be used as a response for called requests
     *
     * @param array $responses List of response instances
     */
    public function setMockResponses(array $responses)
    {
        $this->responses = $responses;
    }
}