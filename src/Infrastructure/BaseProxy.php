<?php

namespace Patagona\Pricemonitor\Core\Infrastructure;

use Patagona\Pricemonitor\Core\Interfaces\HttpClient;

class BaseProxy
{
    const BASE_API_URL = '';

    /** @var array Map of standard HTTP status code/reason phrases */
    private static $statusPhrases = [
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-status',
        208 => 'Already Reported',
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        306 => 'Switch Proxy',
        307 => 'Temporary Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Time-out',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Large',
        415 => 'Unsupported Media Type',
        416 => 'Requested range not satisfiable',
        417 => 'Expectation Failed',
        418 => 'I\'m a teapot',
        422 => 'Unprocessable Entity',
        423 => 'Locked',
        424 => 'Failed Dependency',
        425 => 'Unordered Collection',
        426 => 'Upgrade Required',
        428 => 'Precondition Required',
        429 => 'Too Many Requests',
        431 => 'Request Header Fields Too Large',
        451 => 'Unavailable For Legal Reasons',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Time-out',
        505 => 'HTTP Version not supported',
        506 => 'Variant Also Negotiates',
        507 => 'Insufficient Storage',
        508 => 'Loop Detected',
        511 => 'Network Authentication Required',
    ];

    protected $email;
    protected $password;
    protected $client;

    /**
     * Proxy constructor.
     *
     * @param string $email Pricemonitor account email to use for authorization
     * @param string $password Pricemonitor account password to use for authorization
     * @param \Patagona\Pricemonitor\Core\Interfaces\HttpClient $client Low level HTTP client to use for actual communication
     */
    public function __construct($email, $password, HttpClient $client)
    {
        $this->email = $email;
        $this->password = $password;
        $this->client = $client;
    }

    /**
     * Sends request to Pricemonitor API and decodes response body to PHP array
     *
     * @param string $method HTTP method code to send (GET, POST, PUT...)
     * @param string $endpoint Pricemonitor request endpoint without base PM URL nor query string. Example '/account'
     * @param array $query List of query string parameters to append to the endpoint when building a request URL
     * @param array $body List of body parameters to send as request body
     * @param array $headers Array of headers to send with request. This list will be merged into default headers list that
     * consist of Authorization and Accept headers
     *
     * @return array Decoded response body
     * @throws \Exception
     */
    protected function request($method, $endpoint, array $query = [], array $body = [], array $headers = [])
    {
        $url = $this->buildUrl($endpoint, $query);

        $response = $this->client->request(
            $method, $url,
            $this->buildHeaders($headers),
            !empty($body) ? json_encode($body) : ''
        );

        $statusCode = $response->getStatus();
        if (!$this->isStatusCodeOK($statusCode)) {
            throw $this->buildRequestException($statusCode, $method, $url);
        }

        $content = json_decode($response->getBody(), true);
        if (empty($content)) {
            return [];
        }

        return $content;
    }

    /**
     * Sends async request to Pricemonitor API
     *
     * @param string $method HTTP method code to send (GET, POST, PUT...)
     * @param string $endpoint Pricemonitor request endpoint without base PM URL nor query string. Example '/account'
     * @param array $query List of query string parameters to append to the endpoint when building a request URL
     * @param array $body List of body parameters to send as request body
     * @param array $headers Array of headers to send with request. This list will be merged into default headers list that
     * consist of Authorization and Accept headers
     *
     * @throws \Exception
     */
    protected function requestAsync($method, $endpoint, array $query = [], array $body = [], array $headers = [])
    {
        $this->client->requestAsync(
            $method,
            $this->buildUrl($endpoint, $query),
            $this->buildHeaders($headers),
            !empty($body) ? json_encode($body) : ''
        );
    }

    private function isStatusCodeOK($statusCode)
    {
        return 200 <= $statusCode && $statusCode < 300;
    }

    /**
     * @param $endpoint
     * @param array $query
     *
     * @return string
     */
    private function buildUrl($endpoint, array $query)
    {
        return static::BASE_API_URL . $endpoint . $this->buildQueryString($query);
    }

    /**
     * @param array $query
     *
     * @return string
     */
    private function buildQueryString(array $query)
    {
        $queryString = '';
        if (!empty($query)) {
            $queryString = '?' . http_build_query($query);
        }

        return $queryString;
    }

    /**
     * @param array $headers
     *
     * @return array
     */
    private function buildHeaders(array $headers)
    {
        return array_merge([
            'Authorization' => 'Basic ' . base64_encode("{$this->email}:{$this->password}"),
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ], $headers);
    }

    /**
     * @param $method
     * @param $statusCode
     * @param $url
     *
     * @return \RuntimeException
     */
    protected function buildRequestException($statusCode, $method, $url)
    {
        return new \RuntimeException(
            sprintf(
                'Server responded with %d %s status for %s request to %s',
                $statusCode,
                !empty(self::$statusPhrases[$statusCode]) ? self::$statusPhrases[$statusCode] : '',
                $method,
                $url
            ),
            $statusCode
        );
    }

}