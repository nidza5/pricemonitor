<?php

namespace Patagona\Pricemonitor\Core\Interfaces;

interface HttpResponse
{
    /**
     * Gets response HTTP status code
     *
     * @return int HTTP response status code. For example 200 for "200 OK", 404 for "404 Not found"...
     */
    public function getStatus();

    /**
     * Gets response headers
     *
     * @return array Response headers list where key is header name and value is header value. Example:
     *      [ "content-type" => "application/json; charset=utf-8", "connection" => "keep-alive" ]
     */
    public function getHeaders();

    /**
     * Gets response body string
     *
     * @return string Response payload without any decoding, if payload is in json format for example string representation
     * of that json should be returned.
     */
    public function getBody();
}