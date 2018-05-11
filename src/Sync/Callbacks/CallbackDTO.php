<?php

namespace Patagona\Pricemonitor\Core\Sync\Callbacks;

class CallbackDTO
{
    /** @var  string */
    private $method;
    
    /** @var  string */
    private $name;
    
    /** @var  [] */
    private $bodyTemplate;
    
    /** @var  string */
    private $url;
    
    /** @var  [] */
    private $headers;

    /**
     * @return string
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return array
     */
    public function getBodyTemplate()
    {
        return $this->bodyTemplate;
    }

    /**
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * @return array
     */
    public function getHeaders()
    {
        return $this->headers;
    }
    
    public function __construct($method, $name, array $bodyTemplate, $url, array $headers)
    {
        $this->method = strtoupper($method);
        $this->name = $name;
        $this->bodyTemplate = $bodyTemplate;
        $this->url = $url;
        $this->headers = $headers;
    }
}