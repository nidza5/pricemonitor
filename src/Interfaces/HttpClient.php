<?php

namespace Patagona\Pricemonitor\Core\Interfaces;

interface HttpClient
{
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
    public function request($method, $url, array $headers = [], $body = '');

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
    public function requestAsync($method, $url, array $headers = [], $body = '');
}