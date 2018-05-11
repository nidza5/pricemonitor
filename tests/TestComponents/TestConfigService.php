<?php

namespace Patagona\Pricemonitor\Core\Tests\TestComponents;

use Patagona\Pricemonitor\Core\Interfaces\ConfigService;

class TestConfigService implements ConfigService
{
    public $configMap = [];

    /**
     * Get clients credentials from config
     *
     * @return array
     */
    public function getCredentials()
    {
        return [
            'email' => 'test@example.com',
            'password' => 'test'
        ];
    }

    /**
     * Get clients credentials from config
     *
     * @return string
     */
    public function getComponentName()
    {
        return 'logeecom.shopware';
    }

    /**
     * Get clients credentials from config
     *
     * @return string
     */
    public function getSource()
    {
        return 'shopware.testshop.rs';
    }

    /**
     * Get value from config for given key
     *
     * @param $key
     *
     * @return string
     */
    public function get($key)
    {
        return !empty($this->configMap[$key]) ? $this->configMap[$key] : '';
    }

    /**
     * Create or update config
     *
     * @param $key
     * @param $value
     */
    public function set($key, $value)
    {
        $this->configMap[$key] = $value;
    }

}