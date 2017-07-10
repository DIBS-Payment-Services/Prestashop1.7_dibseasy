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

namespace Invertus\Dibs\Service;

use Exception;
use Guzzle\Http\ClientInterface;
use Guzzle\Http\Exception\ServerErrorResponseException;
use Guzzle\Stream\StreamInterface;
use Invertus\Dibs\Adapter\ToolsAdapter;

/**
 * Class ApiService
 *
 * @package Invertus\Dibs\Service
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
    public function get($url, $params = array())
    {
        $apiResponse = new ApiResponse();

        try {
            $request = $this->client->get($url, array(), $params);
            $response = $request->send();

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
    public function post($url, $params = array())
    {
        $apiResponse = new ApiResponse();

        try {
            $request = $this->client->post($url, array(), $params);
            $response = $request->send();

            $body = $response->getBody()->__toString();
            $body = $this->toolsAdapter->jsonDecode($body);

            $apiResponse->setStatusCode($response->getStatusCode());
            $apiResponse->setBody(is_array($body) ? $body : array());
        } catch (ServerErrorResponseException $e) {
            $response = $e->getResponse();
            $apiResponse->setStatusCode($response->getStatusCode());
        } catch (Exception $e) {
        }

        return $apiResponse;
    }
}
