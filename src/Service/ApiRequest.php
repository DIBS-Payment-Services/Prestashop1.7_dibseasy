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

use Exception;
use GuzzleHttp\ClientInterface;
use Invertus\DibsEasy\Adapter\ToolsAdapter;

/**
 * Class ApiService
 *
 * @package Invertus\DibsEasy\Service
 */
class ApiRequest
{
    /**
     * @var ClientInterface
     */
    private $client;

    /**
     * @var ToolsAdapter
     */
    private $toolsAdapter;

    /**
     * ApiService constructor.
     *
     * @param ClientInterface $client
     * @param ToolsAdapter $toolsAdapter
     */
    public function __construct(ClientInterface $client, ToolsAdapter $toolsAdapter)
    {
        $this->client = $client;
        $this->toolsAdapter = $toolsAdapter;
    }

    /**
     * Make GET request
     *
     * @param string $url
     * @param array|string $params
     *
     * @return ApiResponse
     */
    public function get($url, $params = [])
    {
        $apiResponse = new ApiResponse();

        try {
            $response = $this->client->get($url, $params);

            $body = $response->getBody()->__toString();
            $body = $this->toolsAdapter->jsonDecode($body);

            $apiResponse->setStatusCode($response->getStatusCode());
            $apiResponse->setBody($body);
        } catch (Exception $e) {
        }

        return $apiResponse;
    }

    /**
     * Make POST request
     *
     * @param string $url
     * @param array|string $params
     *
     * @return ApiResponse
     */
    public function post($url, $params = [])
    {
        $apiResponse = new ApiResponse();

        try {
            $response = $this->client->post($url, ['body' => $params]);

            $body = $response->getBody()->__toString();
            $body = $this->toolsAdapter->jsonDecode($body);

            $apiResponse->setStatusCode($response->getStatusCode());
            $apiResponse->setBody(is_array($body) ? $body : []);
        } catch (Exception $e) {
        }

        return $apiResponse;
    }

    /**
     * @param string $url
     * @param array|string $params
     *
     * @return ApiResponse
     */
    public function put($url, $params = [])
    {
        $apiResponse = new ApiResponse();

        try {
            $response = $this->client->put($url, ['body' => $params]);

            $body = $response->getBody()->__toString();
            $body = $this->toolsAdapter->jsonDecode($body);

            $apiResponse->setStatusCode($response->getStatusCode());
            $apiResponse->setBody(is_array($body) ? $body : []);
        } catch (Exception $e) {
        }

        return $apiResponse;
    }
}
