<?php

namespace Patagona\Pricemonitor\Core\Interfaces;

interface ConfigService
{
    /**
     * Get clients credentials from config
     *
     * @return array
     */
    public function getCredentials();

    /**
     * Get clients credentials from config
     *
     * @return string
     */
    public function getComponentName();

    /**
     * Get clients credentials from config
     *
     * @return string
     */
    public function getSource();

    /**
     * Get value from config for given key
     *
     * @param $key
     *
     * @return string
     */
    public function get($key);

    /**
     * Create or update config
     *
     * @param $key
     * @param $value
     */
    public function set($key, $value);
}