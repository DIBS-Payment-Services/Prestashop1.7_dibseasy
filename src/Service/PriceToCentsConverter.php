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

/**
 * Converts total price to cents
 */
class PriceToCentsConverter
{
    /**
     * @param float $totalPrice
     *
     * @return int
     */
    public static function convert($totalPrice)
    {
        return (int) (string) ((float) $totalPrice * 100);
    }
}
