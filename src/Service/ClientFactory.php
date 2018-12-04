<?php
/**
 * 2016 - 2017 Invertus, UAB
 *
 * NOTICE OF LICENSE
 *
 * This file is proprietary and can not be copied and/or distributed
 * without the express permission of INVERTUS, UAB
 *
 * @author    INVERTUS, UAB www.invertus.eu <support@invertus.eu>
 * @copyright Copyright (c) permanent, INVERTUS, UAB
 * @license   Addons PrestaShop license limitation
 *
 * International Registered Trademark & Property of INVERTUS, UAB
 */

namespace Invertus\DibsEasy\Service;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Invertus\DibsEasy\Adapter\ConfigurationAdapter;

/**
 * Class ClientFactory
 *
 * @package Invertus\DibsEasy\Service
 */
class ClientFactory
{
    /**
     * @var ConfigurationAdapter
     */
    private $configurationAdapter;

    /**
     * @var string
     */
    private $testUrl;

    /**
     * @var string
     */
    private $prodUrl;

    /**
     * ClientFactory constructor.
     *
     * @param ConfigurationAdapter $configurationAdapter
     * @param string $testUrl
     * @param string $prodUrl
     */
    public function __construct(ConfigurationAdapter $configurationAdapter, $testUrl, $prodUrl)
    {
        $this->configurationAdapter = $configurationAdapter;
        $this->testUrl = $testUrl;
        $this->prodUrl = $prodUrl;
    }

    /**
     * Create new Guzzle client
     *
     * @return ClientInterface
     */
    public function createNew()
    {
        $isTestModeOn = (bool) $this->configurationAdapter->get('DIBS_TEST_MODE');
        $baseUrl = $isTestModeOn ? $this->testUrl : $this->prodUrl;
        $auhorizationKey = $isTestModeOn ?
            $this->configurationAdapter->get('DIBS_TEST_SECRET_KEY') :
            $this->configurationAdapter->get('DIBS_PROD_SECRET_KEY');

        $config = [
            'base_url' => $baseUrl,
            'defaults' => [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'Authorization' => $auhorizationKey,
                    'commercePlatformTag' => 'PSEasy',
                ],
            ],
        ];

        $client = new Client($config);

        return $client;
    }
}
